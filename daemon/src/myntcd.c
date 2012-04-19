/*
** MYNTC Daemon
**
** A program leforditasa:
**	cc -lpcap -lpthread myntcd.c -o myntcd
**	vagy optimalizalva
**	cc -D'CONF_DIR="/usr/local/etc/myntcd"' -D'CONF_FILE="myntcd.conf"' -O2 -lpcap -lpthread myntcd.c -o myntcd
**
** A program leforditasahoz a libpcap0.8 es libpcap0.8-dev csomagok szuksegesek
**
** A program mukodesi abraja:
**
**	main() --> process_options() --> usage()
**	     |
**           + --> read_config() --> nmbtonm()
**           |
**	     + --> daemon_start()
**           |
**	     + --> do_pid_file()
**           |
**           + --> signal_setup() --> daemon_stop()
**           |                  |
**           |                  + --> alarm_handler() --> write_data() --> clear_count()
**           |                  |
**           |                  + --> reload_config() --> exit_capture()
**           |                                      |
**           |                                      + --> write_data() --> clear_count()
**           |                                      |
**           |                                      + --> free_mem_cfg()
**           |                                      |
**           |                                      + --> read_config()
**           |                                      |
**           + --> init_capture()                   + --> init_capture()
**           |
**           + --> do_acct() ----------------------------------------> get_data_from_queue() --> handle_frame() --> register_packet()
**	     |		   |								   |					    |
**	     |		   + --> fifo_init()						  fifo					    |
**	     |		   |								   |					    |
**	     |		   + --> packet_loop() --> process_packet() --> send_data_to_queue()					    |
**           |                                                                  			   			    |
**           + --> exit_capture()                                               			   			    + --> onnetlist()
**           |                                                                  			   			    |
**	     + --> free_mem_cfg()									   			    + --> onnetlist6()
**	     								    				   			    |
**                                                      		    				   			    + --> add_ipv4_addr() --> clear_count()
**													   			    |			|
**													   			    |			+ --> register_packet()
**                                                                          				   			    |
**                                                                          				   			    + --> add_ipv6_addr() --> clear_count()
**																			|
**																			+ --> register_packet()
**
**
*/

#ifdef HAVE_CONFIG_H
#include <config.h>
#endif
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <signal.h>
#include <syslog.h>
#include <ctype.h>
//#include <inttypes.h>
// #include <netdb.h>
#include <net/ethernet.h>
#include <net/if.h>
//#include <sys/socket.h>
//#include <sys/ioctl.h>
//#include <linux/if_ether.h>
//#include <asm/types.h>
#include <netinet/ip.h>
#include <netinet/in.h>
//#include <arpa/inet.h>
#include <netinet/udp.h>
#include <netinet/tcp.h>
#include <netinet/ip_icmp.h>
#include <netinet/ip6.h>
#include <netinet/icmp6.h>
#include <errno.h>
#include <time.h>

#include <sys/wait.h>

#include <pcap.h>
#include <pthread.h>

#define HASH_SIZE 4096

#if !defined(CONF_DIR)
#define CONF_DIR "/usr/local/etc/myntcd"
#endif

#if !defined(CONF_FILE)
#define CONF_FILE "myntcd.conf"
#endif

#define CONFIG CONF_DIR"/"CONF_FILE

#define PCAP_SNAPLEN 128
#define PCAP_TMOUT 1000

#define NUM_THREADS 2

#define IPV4_BINADDRLEN 32
#define IPV6_BINADDRLEN 128
#define IPV4_BINQUADPERNETMASK 8
#define IPV6_BINOCTETPERNETMASK 16

#define IPPROTO_MH 135


// a config fajlbol beolvasott adatokat tarolja
struct config
{
    char *pid_file; // a futo alkalmazas azonositojat
    char *dir; // a forgalmi adatokat ide mentjuk
    char *prefix; // a mentett fajlnevenek elotagja
    unsigned short int sniff; // bele nezunk e az osszes csomagokba (nem csak azt latjuk amit mi kapunk) 1 igen 0 nem
    unsigned short int save_interval; // mentesi intervallum masodpercben
    struct promisc_device *promisc; // halokartya amin figyelunk
    struct headerdat *headers; // a halokartya adatai
    struct my_netlist *mynet; // ipv4-es tartomanyok, amit figyelni fogunk
    struct my_netlist6 *mynet6; // ipv6-os tartomanyok, amit figyelni fogunk
};

// forgalom szamolasahoz valtozok
struct ipdata
{
    // TCP csomagok
    unsigned long t_count;
    uint64_t t_bytes;
    // UDP csomagok
    unsigned long u_count;
    uint64_t u_bytes;
    // ICMP csomagok
    unsigned long i_count;
    uint64_t i_bytes;
    // minden mas
    unsigned long o_count;
    uint64_t o_bytes;
};

struct my_netlist
{
    struct in_addr addr, mask;
    struct my_netlist *next;
};

struct my_netlist6
{
    struct in6_addr addr, prefixaddr;
    struct my_netlist6 *next;
};

// a lista alapjan megfigyelt ipv4-es cimeket taroja
struct my_network
{
    struct in_addr addr;
    struct ipdata in;
    struct ipdata out;
    struct my_network *next;
};

// a lista alapjan megfigyelt ipv6-es cimeket taroja
struct my_network6
{
    struct in6_addr addr;
    struct ipdata in;
    struct ipdata out;
    struct my_network6 *next;
};

struct promisc_device
{
    char *name; // (pl. eth0)
    unsigned short int reset;
    struct ifreq oldifr; // regi beallitas
};

struct headerdat
{
    char *name;
    unsigned short int l;
    unsigned short int offset;
    unsigned short int type;
};

struct node
{
    int af;
    int protonum;
    int packetlen;
    struct in_addr sa, da;
    struct in6_addr sa6, da6;
    struct node *next;
};

// blokkolas nelkuli fifo-t hasznalunk a csomaggyujtes es a feldolgozas kozott
struct queue
{
    struct node *head;
    struct node *tail;
};

char *fname = NULL; // a config fajl helyet es nevet tarolja
struct config *cfg; // a config fajlbol beolvasott adatokat tarolja el
struct my_network *my_ipv4_net; // az eszlelt ipv4-es cimeket tarolja el a forgalmi adatokkal
struct my_network6 *my_ipv6_net; // az eszlelt ipv6-es cimeket tarolja el a forgalmi adatokkal
struct queue *fifo; // pufferbe tarolja el a csomagokat, amig fel nem lesz dolgozva
unsigned int daem = 1; // demonkent fusson a program
volatile time_t start_log, now; // pontos ido
pcap_t *pds; // interfeszenkent a csomagleirok tombje

char *nmbtonm(int, int); // a megadott bitet netmaszkka alakitja
void err_quit(void); // hiba eseten hibauzenetet ir ki
struct config *read_config(char *); // beolvassa a config fajlt
void free_mem_cfg(struct config *exit_cfg); // felszabaditja a lefoglalt memoriat
void reload_config(int); // ujrainditas eseten ujraolvassa a config fajlt
void init_capture(void); // elinditja a csomag figyelest
void exit_capture(void); // leallitja a csomag figyelest
void fifo_init(void); // lefoglalja a memoriat a puffernek
void do_acct(void); // tobbszalu feldolgozas inditasa
void clear_count(int, void *); // nullazza a csomagszamlalokat
int onnetlist(struct in_addr addr); // megnezi hogy a csomagbol kiolvasott ipv4-es cim benne van e figyelt tartomanyban
int onnetlist6(struct in6_addr addr); // megnezi hogy a csomagbol kiolvasott ipv6-es cim benne van e figyelt tartomanyban
void add_ipv4_addr(struct in_addr addr, int, int, char *); // felvesszuk az ipv4-es cimet a listankra es szamoljuk a forgalmat
void add_ipv6_addr(struct in6_addr addr, int, int, char *); // felvesszuk az ipv6-es cimet a listankra es szamoljuk a forgalmat
void register_packet(int, void *, int, int, char *); // a csomagszamlalokat kezeli
void handle_frame(int, int, int, struct in_addr sa, struct in_addr da, struct in6_addr sa6, struct in6_addr da6); // a pufferbol kiolvasott adatokat dolgozza fel
void send_data_to_queue(int, unsigned char *, int); // a csomagbol kiolvassa az adatokat es tovabbkuldi a puffernek
void *get_data_from_queue(); // a pufferbol kiolvasott adatokat tovabbkuldi feldolgozasra
void process_packet(u_char *args, const struct pcap_pkthdr *header, const u_char *packet); // szortirozza a csomagokat ipv4/ipv6 szerint majd tovabbadja feldolgozasra
void *packet_loop(); // a folyamatos csomagfeldolgozasert felel
int do_pid_file(void); // a program azonositojat kezeli
int daemon_start(void); // demonken inditja a programot vagyis a hatterben fut a program
void daemon_stop(int); // demont leallitja
void write_data(struct config *conf, time_t stime, time_t ltime); // kiirja a memoriaban tarolt adatokat egy fajlba
void alarm_handler(int); // megszakitasokert felel, adott idonkent meghivja a write_data fuggvenyt
void signal_ignore(int);
void signal_setup(void); // demon modba a signalokat kezeli
void usage(char *); // kiirja hogyan hasznaljuk a programot konzolos modba
void process_options(int, char **); // konzolos mod lekezelese


/*
 A regi esetben mikor hexaban szamolta a netmaszkot az nem volt teljesen jo,
 mert ami nem volt 8-al oszthato rossz nemegesz erteket kapott.
 Ez a fuggveny a megadott /24-et atalakitja binarissa es utanna szamolja ki
 a netmaszkot, ami mar a jo erteket adja vissza.
*/
char *nmbtonm(int af, int bits) // netmaszk bit => netmaszk
{
    int i;
    char temp[5];
    int binaddr[IPV6_BINADDRLEN];
    static char straddr[INET6_ADDRSTRLEN];
    int x, y;
    int ipbinaddrlen;
    int pieceofnetmasklen;

    if(af == AF_INET)
    {
	ipbinaddrlen = IPV4_BINADDRLEN;
	pieceofnetmasklen = IPV4_BINQUADPERNETMASK;
    }
    if(af == AF_INET6)
    {
	ipbinaddrlen = IPV6_BINADDRLEN;
	pieceofnetmasklen = IPV6_BINOCTETPERNETMASK;
    }

    // a megadott ertekig egyest irunk utanna nullat
    for(i = 0; i < ipbinaddrlen; i++)
    {
	if(i < bits)
	{
	    binaddr[i] = 1;
	}
	else
	{
	    binaddr[i] = 0;
	}
    }

    y = pieceofnetmasklen - 1; // milyen hosszu a netmaszk/prefix cim egy szakasza '.' vagy ':'-ig
    x = 0;
    straddr[0] = '\0';
    // vegigmegyunk a binaris tombunkon
    for(i = 0; i < ipbinaddrlen; i++)
    {
	// osszeadjuk az adott hosszig a ketto hatvanyait helyiertek szerint
	x += binaddr[i] << y;

	// ha elfogynak az osszeadando szamok, egy resz vegere erunk
	if(y == 0)
	{
	    // kitevo visszakapja erteket, a legnagyobb helyierteku szam erteket kapja meg
	    y = pieceofnetmasklen;
	    // ha IPv4
	    if(af == AF_INET)
	    {
		sprintf(temp, "%d", x);
	    }
	    if(af == AF_INET6) // ha IPv6
	    {
		sprintf(temp, "%X", x);
	    }
	    x = 0;
	    if(i == (pieceofnetmasklen - 1))
	    {
		// bemasoljuk az elso erteket
		strcpy(straddr, temp);
		// elvalaszto jelet kirakjuk
		if(af == AF_INET)
		{
		    strcat(straddr, ".");
		}
		if(af == AF_INET6)
		{
		    strcat(straddr, ":");
		}
	    }
	    else
	    {
		// tobbi erteket masoljuk
		strcat(straddr, temp);
		// a vegen mar nem rakunk elvalaszto jelet
		if(i != (ipbinaddrlen - 1))
		{
		    if(af == AF_INET)
		    {
			strcat(straddr, ".");
		    }
		    if(af == AF_INET6)
		    {
			strcat(straddr, ":");
		    }
		}
	    }
	}
	y--;
    }
    return straddr;
}

void err_quit(void) {
        // hibauzenet.
        fprintf(stderr, "myntcd didn't start. Read syslog.\n");
}

struct config *read_config(char *fname)
{
    char buff[1024];
    // config file
    FILE *cf;
    unsigned short int line = 0;
    // a konfiguraciot tarolo strukturara mutato pointer
    struct config *new_cfg = malloc(sizeof(struct config));
    // sikertelen memoriafoglalas eseten return null
    if(new_cfg  == NULL)
    {
	return new_cfg;
    }
    // feltoltjuk az ertekeket
    new_cfg -> pid_file = NULL;
    new_cfg -> dir = NULL;
    new_cfg -> prefix = NULL;
    new_cfg -> sniff = 0;
    new_cfg -> save_interval = 0;
    new_cfg -> promisc = NULL;
    new_cfg -> headers = NULL;
    new_cfg -> mynet = NULL;
    new_cfg -> mynet6 = NULL;
    // konfigfilet olvasasra megnyit
    cf = fopen(fname, "r");
    if(cf == NULL)
    {
	syslog(LOG_ERR, "config file: no such file or directory\n");
	err_quit();
	return NULL;
    }

    while(fgets(buff, sizeof(buff), cf))
    {
        // kitoroljuk a sorvege karaktereket
        char *cmt = strchr(buff, '\n');
        if(cmt)
        {
    	    *cmt = '\0';
    	}
        line++;
        // kitoroljuk a kommenteket is
        cmt = strchr(buff, '#');
        if(cmt)
        {
    	    *cmt = '\0';
    	}
        // kitoroljuk a vezeto whitespacekat is
        while(isspace(*buff))
        {
            memmove(buff, buff + 1, strlen(buff));
        }
        // kitoroljuk a sor vegi whitespaceket is
	cmt = strchr(buff, '\0');
        cmt--;
        while(isspace(*cmt))
        {
            *cmt = '\0';
            cmt--;
        }
        // nem ures sorokat feldolgozzuk
        if(*buff) {
            char *kwd = buff; // egy sort megkap
            char *value = buff + strcspn(buff," \t"); // szokoz, vagy tabulator helyevel eltoljuk vagyis megkapjuk az erteket
            *value++ = '\0'; // szokoz, vagy tabulatort levagja
            while(isspace(*value))
            {
        	value++; // ha van meg szokoz annyival eltolja hogy ne legyen
    	    }
    	    // interface, amin figyel a program
            if(strcasecmp(kwd, "device") == 0)
            {
                struct promisc_device *tmp;
                syslog(LOG_DEBUG, "config: DEVICE: %s\n", strdup(value));

                tmp = malloc(sizeof(struct promisc_device));
                if(tmp != NULL)
                {
                    tmp->name  = strdup(value);
                    tmp->reset = 0;
                    new_cfg->promisc = tmp;
                    syslog(LOG_DEBUG, "config: added promiscous device %s\n", new_cfg->promisc->name);
                }
            }
            // belenezzunk e a csomagba
            else if(strcasecmp(kwd, "sniff") == 0)
            {
                new_cfg->sniff = atoi(value);
                syslog(LOG_DEBUG,"config: sniff set to %d",new_cfg->sniff);
            }
            // kiolvassuk az interface tulajdonsagait
            else if(strcasecmp(kwd, "headers") == 0)
            {
                char *offset;
                char *type;
                struct headerdat *tmp;

                offset  = value + strcspn(value," \t");
                *offset++ = '\0';
                while(isspace(*offset))
                {
            	    offset++;
            	}

                type  = offset + strcspn(offset," \t");
                *type++ = '\0';
                while(isspace(*type))
                {
            	    type++;
            	}

                tmp = malloc(sizeof(struct headerdat));

                if(tmp != NULL)
                {
                    tmp->name = strdup(value);
                    tmp->l = strlen(value);
                    tmp->offset = atoi(offset);
                    tmp->type = atoi(type);
                    new_cfg->headers = tmp;
                    syslog(LOG_DEBUG, "config: added headerinfo (%s:%d:%d)\n", tmp->name, tmp->offset, tmp->type);
                }
            }
            // melyik halozatokat figyeljuk
            else if(strcasecmp(kwd, "mynetwork") == 0 || strcasecmp(kwd, "mynetwork6") == 0)
            {
		struct in_addr ipaddr, mask;
		struct in6_addr ip6addr, prefixaddr;
		static char straddr[INET_ADDRSTRLEN];
		static char strmask[INET_ADDRSTRLEN];
		static char straddr6[INET6_ADDRSTRLEN];
		static char strpref6[INET6_ADDRSTRLEN];

		char **ip = (char**)malloc(sizeof(char*));
		int ipk = 0;
		char *temp, *temp2;
		temp = temp2 = value;

		while(1)
		{
		    // ha a mynetwork utan tobb ip cimet is talal, akkor elmenti egy tombbe
		    while (*temp != '\t' && *temp != ' ' && *temp != '\0')
		    {
			temp++;
		    }
		    ip = (char**)realloc(ip, ++ipk * sizeof(char*));
		    ip[ipk - 1] = (char*)strndup(temp2, temp - temp2);
		    while(isspace(*temp))
		    {
			temp++;
		    }
		    temp2 = temp;
		    if(temp - value == strlen(value))
		    {
			break;
		    }
		}

		int i, j;

		for(i = 0; i <  ipk; i++)
		{
		    // a / jelnel szetvalasszuk az ip cimet es a netmaszkot
		    temp = strtok(ip[i], "/");
		    temp2 = strtok(NULL, "/");
		    // ha /24 stb. adtunk meg atalakitjuk netmaszkka: /24 => 255.255.255.0
		    if(strlen(temp2) <= 2)
		    {
			if(strcasecmp(kwd, "mynetwork") == 0)
			{
			    temp2 = nmbtonm(AF_INET, atoi(temp2));
			}
			else
			{
			    temp2 = nmbtonm(AF_INET6, atoi(temp2));
			}
		    }
		    // atalakitjuk az ipcimet network byte order-re, vagyis olyan szamma, amit a csomagokba is talalunk
                    if(strcasecmp(kwd, "mynetwork") == 0) // ha IPv4
		    {
			inet_pton(AF_INET, temp, &ipaddr);

			inet_pton(AF_INET, temp2, &mask);
		    }
		    else // ha IPv6
		    {
			inet_pton(AF_INET6, temp, &ip6addr);
			temp[0] = '\0';
			for(j = 0; j < sizeof(ip6addr.s6_addr); j++)
			{
			    sprintf(temp, "%s%u", temp, ip6addr.s6_addr[j]);
			}

			inet_pton(AF_INET6, temp2, &prefixaddr);
			temp2[0] = '\0';
			for(j = 0; j < sizeof(prefixaddr.s6_addr); j++)
			{
			    sprintf(temp2, "%s%u", temp2, prefixaddr.s6_addr[j]);
			}
		    }
                    // elmentjuk a beolvasott tartomanyt a memoriaba
		    if(strcasecmp(kwd, "mynetwork") == 0) // IPv4
		    {
			struct my_netlist *tmp;
			tmp = malloc(sizeof(struct my_netlist));
			if(tmp != NULL)
			{
			    tmp->addr = ipaddr;
			    tmp->mask = mask;
			    tmp->next = new_cfg->mynet;
			    new_cfg->mynet = tmp;
			    inet_ntop(AF_INET, &tmp->addr, straddr, sizeof(straddr));
			    inet_ntop(AF_INET, &tmp->mask, strmask, sizeof(strmask));
			    syslog(LOG_DEBUG, "config: added mynetwork  %s/%s, %X/%X, nbo: %lu/%lu, hbo: %lu/%lu\n", 
						    straddr, 
						    strmask, 
						    (int)ntohl(tmp->addr.s_addr), 
						    (int)ntohl(tmp->mask.s_addr), 
						    (unsigned long)tmp->addr.s_addr, 
						    (unsigned long)tmp->mask.s_addr, 
						    (unsigned long)ntohl(tmp->addr.s_addr), 
						    (unsigned long)ntohl(tmp->mask.s_addr));
			}
		    }
		    else // IPv6
		    {
			struct my_netlist6 *tmp;
			tmp = malloc(sizeof(struct my_netlist6));
			if(tmp != NULL)
			{
			    tmp->addr = ip6addr;
			    tmp->prefixaddr = prefixaddr;
			    tmp->next = new_cfg->mynet6;
			    new_cfg->mynet6 = tmp;
			    inet_ntop(AF_INET6, &tmp->addr, straddr6, sizeof(straddr6));
			    inet_ntop(AF_INET6, &tmp->prefixaddr, strpref6, sizeof(strpref6));
			    syslog(LOG_DEBUG, "config: added mynetwork6 %s/%s, nbo: %s/%s\n", straddr6, strpref6, temp, temp2);
			}
		    }
		}
	    }
	    // mennyi idonkent mentsunk
	    else if(strcasecmp(kwd, "save_interval") == 0)
	    {
		new_cfg->save_interval = atoi(value);
		syslog(LOG_DEBUG, "config: set save interval: %d\n", new_cfg->save_interval);
	    }
	    // program azonositoja
	    else if(strcasecmp(kwd, "pid") == 0)
	    {
		new_cfg->pid_file = strdup(value);
		syslog(LOG_DEBUG, "config: set pid filename to %s\n", new_cfg->pid_file);
	    }
	    // hova mentsuk az adatokat
	    else if(strcasecmp(kwd, "dir") == 0)
	    {
		new_cfg->dir = strdup(value);
		syslog(LOG_DEBUG, "config: set data directory to %s\n", new_cfg->dir);
	    }
	    // a fajl elotagja
	    else if(strcasecmp(kwd, "prefix") == 0)
	    {
		new_cfg->prefix = strdup(value);
		syslog(LOG_DEBUG, "config: set data filename prefix to %s\n", new_cfg->prefix);
	    }
	}
    }
    // ha valamit nem sikerult elmenteni
    if(new_cfg->promisc == NULL)
    {
	syslog(LOG_ERR, "config file: no device given\n");
	err_quit();
	return NULL;
    }

    if(new_cfg->headers == NULL)
    {
	syslog(LOG_ERR, "config file: no header information given\n");
	err_quit();
	return NULL;
    }

    if(new_cfg->mynet == NULL && new_cfg->mynet6 == NULL)
    {
	syslog(LOG_ERR, "config file: no mynetwork or mynetwork6 given\n");
	err_quit();
	return NULL;
    }

    if(new_cfg->prefix == NULL)
    {
	syslog(LOG_ERR, "config file: no prefix given\n");
	err_quit();
	return NULL;
    }

    if(new_cfg->pid_file == NULL)
    {
	syslog(LOG_ERR, "config file: no pid file given\n");
	err_quit();
	return NULL;
    }

    fclose(cf);
    return new_cfg;
}

void free_mem_cfg(struct config *exit_cfg)
{
    if(exit_cfg)
    {
        if(exit_cfg->mynet)
        {
            struct my_netlist *p1, *p2;
            for(p1 = exit_cfg->mynet; p1; p1 = p2)
            {
                p2 = p1->next;
                free(p1);
            }

            struct my_network *q1, *q2;
            for(q1 = my_ipv4_net; q1; q1 = q2)
            {
        	q2 = q1->next;
        	free(q1);
            }
        }
        if(exit_cfg->mynet6)
        {
            struct my_netlist6 *p1, *p2;
            for(p1 = exit_cfg->mynet6; p1; p1 = p2)
            {
                p2 = p1->next;
                free(p1);
            }

            struct my_network6 *q1, *q2;
            for(q1 = my_ipv6_net; q1; q1 = q2)
            {
        	q2 = q1->next;
        	free(q1);
            }
        }
        free(exit_cfg->pid_file);
        free(exit_cfg->dir);
        free(exit_cfg->prefix);
        free(exit_cfg->headers->name);
        free(exit_cfg->headers);
        free(exit_cfg->promisc->name);
        free(exit_cfg->promisc);
        free(exit_cfg);
    }
}

void reload_config(int sig)
{
    struct config *old_cfg, *new_cfg;

    // megmondjuk a sysolognak, hogy ujratoltodunk
    syslog(LOG_INFO,"Re-Load config file!\n");
    // a jelenlegi konfigot attoltjuk old_cfg-be
    old_cfg = cfg;
    // leallitjuk a csomaglopast
    exit_capture();
    // kiirjuk az adatainkat is
    write_data(old_cfg, start_log, time(NULL));
    // felszabaditjuk a regi konfiguracionak foglalt helyet
    free_mem_cfg(old_cfg);
    // az ujat meg beolvassuk a configfilebol
    new_cfg = read_config(fname);
    // frissitjuk az aktualis konfigot
    cfg = new_cfg;
    // es folytatjuk a lopkodast
    init_capture();
}

void init_capture(void)
{
    char perrbuff[PCAP_ERRBUF_SIZE];
    struct promisc_device *p;

    p = cfg->promisc;
    // megnyitjuk az interface-ket
    if (p != NULL)
    {
        // promisc mod
        pds = pcap_open_live(p -> name, PCAP_SNAPLEN, cfg->sniff, PCAP_TMOUT, perrbuff);
        if(pds == NULL)
        {
            // ha nem megy, azt tudatjuk
            syslog(LOG_ERR, "can't pcap_open_live: %s\n", perrbuff);
            err_quit();
            // majd kilepunk
            daemon_stop(0);
        }
    }
}

void exit_capture(void)
{
    if(pds != NULL)
    {
        // koszonjuk szepen nem kerunk tobb csomagot
        pcap_close(pds);
        // ez meg itten a jo oreg packet interface-k leallitasa
        pds = NULL;
    }
}

void fifo_init(void)
{
    struct queue *tmp;
    tmp = malloc(sizeof(struct queue));
    if(tmp != NULL)
    {
	tmp->head = NULL;
	tmp->tail = NULL;
	fifo = tmp;
    }
    else
    {
	syslog(LOG_ERR, "fifo_init: memory allocation failed\n");
	err_quit();
        daemon_stop(0);
    }
}

void do_acct(void)
{
    pthread_t threads[NUM_THREADS];
    pthread_attr_t attr;
    int i;
    void *status;
    struct promisc_device *p;

    p = cfg->promisc;
    start_log = now = time(NULL); // inicializaljuk az idot
    alarm(1); // a megadott ertek mp-ben eltelt ido utan egy SIGALRM signalt general, ami a alarm_handler()-t hivja meg

    if (p != NULL)
    {
	fifo_init(); // memoriat foglalunk a puffernek
	// Initialize and set thread detached attribute 
	pthread_attr_init(&attr);
	pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_JOINABLE);
	// az alabbi ket fuggveny parhuzamosan fog futni
	if (pthread_create(&threads[0], &attr, packet_loop, NULL))
	{
	    syslog(LOG_ERR, "do_acct: pthread_create(&threads[0], NULL, packet_loop, NULL) failed\n");
	    err_quit();
	    daemon_stop(0);
	}
	sleep(5); // 5 masodperc elteltevel inditjuk a masodik fuggvenyt is
	if (pthread_create(&threads[1], &attr, get_data_from_queue, NULL))
	{
	    syslog(LOG_ERR, "do_acct: pthread_create(&threads[1], NULL, get_data_from_queue, NULL) failed\n");
	    err_quit();
	    daemon_stop(0);
	}
    }
    
    // Free attribute and wait for the other threads
    pthread_attr_destroy(&attr);
    for(i = 0; i < NUM_THREADS; i++)
    {
	if (pthread_join(threads[i], &status)) {
	    syslog(LOG_ERR, "do_acct: pthread_join(threads[i], &status) failed\n");
	    err_quit();
	    daemon_stop(0);
        }      
    }
    
    pthread_exit(NULL);
}

void clear_count(int af, void *myn)
{
    if(af == AF_INET)
    {
	struct my_network *tmp;
	tmp = myn;

	tmp->in.t_count = tmp->in.t_bytes = 0;
    	tmp->in.u_count = tmp->in.u_bytes = 0;
    	tmp->in.i_count = tmp->in.i_bytes = 0;
    	tmp->in.o_count = tmp->in.o_bytes = 0;

    	tmp->out.t_count = tmp->out.t_bytes = 0;
    	tmp->out.u_count = tmp->out.u_bytes = 0;
	tmp->out.i_count = tmp->out.i_bytes = 0;
    	tmp->out.o_count = tmp->out.o_bytes = 0;
    }
    if(af == AF_INET6)
    {
	struct my_network6 *tmp;
	tmp = myn;

	tmp->in.t_count = tmp->in.t_bytes = 0;
    	tmp->in.u_count = tmp->in.u_bytes = 0;
    	tmp->in.i_count = tmp->in.i_bytes = 0;
    	tmp->in.o_count = tmp->in.o_bytes = 0;

    	tmp->out.t_count = tmp->out.t_bytes = 0;
    	tmp->out.u_count = tmp->out.u_bytes = 0;
	tmp->out.i_count = tmp->out.i_bytes = 0;
    	tmp->out.o_count = tmp->out.o_bytes = 0;
    }
}

// megnezi hogy az ip cim szerepel e a szurt ip tartomanyok kozott
int onnetlist(struct in_addr addr)
{
    struct my_netlist *netlist;
    netlist = cfg->mynet;

    while(netlist != NULL)
    {
	// a csomagban talalt ip cimet a config fajl listajaban megadott tartomanyok netmaszkjaval ES muveletet vegez
	// ha egyezik tartomany ip cimevel akkor feldolgozzuk
	if((addr.s_addr & netlist->mask.s_addr) == netlist->addr.s_addr)
	{
	    return 1;
	}
	netlist = netlist->next;
    }
    return 0;
}
// ua. ipv6-ra
int onnetlist6(struct in6_addr addr)
{
    struct my_netlist6 *netlist;
    netlist = cfg->mynet6;
    int i, count;

    while(netlist != NULL)
    {
	for(i = 0, count = 0; i < sizeof(netlist->addr.s6_addr); i++)
	{
	    if((addr.s6_addr[i] & netlist->prefixaddr.s6_addr[i]) == netlist->addr.s6_addr[i])
	    {
		count++;
	    }
	}
	if(count == sizeof(netlist->addr.s6_addr))
	{
	    return 1;
	}
	netlist = netlist->next;
    }
    return 0;
}

void add_ipv4_addr(struct in_addr addr, int packetlen, int protonum, char *saorda)
{
    struct my_network *tmp;
    tmp = malloc(sizeof(struct my_network));
    if(tmp != NULL)
    {
	tmp->addr = addr;

    	clear_count(AF_INET, tmp);

	register_packet(AF_INET, tmp, packetlen, protonum, saorda);

	tmp->next = my_ipv4_net;
	my_ipv4_net = tmp;
    }
}

void add_ipv6_addr(struct in6_addr addr, int packetlen, int protonum, char *saorda)
{
    struct my_network6 *tmp;
    tmp = malloc(sizeof(struct my_network6));
    if(tmp != NULL)
    {
	tmp->addr = addr;

    	clear_count(AF_INET6, tmp);

	register_packet(AF_INET6, tmp, packetlen, protonum, saorda);

	tmp->next = my_ipv6_net;
	my_ipv6_net = tmp;
    }
}

void register_packet(int af, void *myn, int packetlen, int protonum, char *saorda)
{
    if(af == AF_INET)
    {
	struct my_network *tmp;
	tmp = myn;

	switch (protonum) //Check the Protocol and do accordingly...
	{
    	    case IPPROTO_ICMP:  //ICMP Protocol (protocol number: 1)
    	    case IPPROTO_ICMPV6: //ICMPv6 Protocol (protocol number: 58)
        	if(saorda == "sa")
        	{
        	    tmp->out.i_count++;
        	    tmp->out.i_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.i_count++;
        	    tmp->in.i_bytes += packetlen;
        	}
        	break;
    	    case IPPROTO_TCP:  //TCP Protocol (protocol number: 6)
        	if(saorda == "sa")
        	{
        	    tmp->out.t_count++;
    		    tmp->out.t_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.t_count++;
    		    tmp->in.t_bytes += packetlen;
        	}
        	break;
    	    case IPPROTO_UDP: //UDP Protocol (protocol number: 17)
        	if(saorda == "sa")
        	{
        	    tmp->out.u_count++;
        	    tmp->out.u_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.u_count++;
        	    tmp->in.u_bytes += packetlen;
        	}
        	break;
    	    default: //Some Other Protocol like ARP etc.
    		if(saorda == "sa")
        	{
        	    tmp->out.o_count++;
        	    tmp->out.o_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.o_count++;
        	    tmp->in.o_bytes += packetlen;
        	}
        	break;
	}
    }
    if(af == AF_INET6)
    {
	struct my_network6 *tmp;
	tmp = myn;

	switch (protonum) //Check the Protocol and do accordingly...
	{
    	    case IPPROTO_ICMP:  //ICMP Protocol (protocol number: 1)
    	    case IPPROTO_ICMPV6: //ICMPv6 Protocol (protocol number: 58)
        	if(saorda == "sa")
        	{
        	    tmp->out.i_count++;
        	    tmp->out.i_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.i_count++;
        	    tmp->in.i_bytes += packetlen;
        	}
        	break;
    	    case IPPROTO_TCP:  //TCP Protocol (protocol number: 6)
        	if(saorda == "sa")
        	{
        	    tmp->out.t_count++;
    		    tmp->out.t_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.t_count++;
    		    tmp->in.t_bytes += packetlen;
        	}
        	break;
    	    case IPPROTO_UDP: //UDP Protocol (protocol number: 17)
        	if(saorda == "sa")
        	{
        	    tmp->out.u_count++;
        	    tmp->out.u_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.u_count++;
        	    tmp->in.u_bytes += packetlen;
        	}
        	break;
    	    default: //Some Other Protocol like ARP etc.
    		if(saorda == "sa")
        	{
        	    tmp->out.o_count++;
        	    tmp->out.o_bytes += packetlen;
        	}
        	else if(saorda == "da")
        	{
        	    tmp->in.o_count++;
        	    tmp->in.o_bytes += packetlen;
        	}
        	break;
	}
    }
}

void handle_frame(int af, int protonum, int packetlen, struct in_addr sa, struct in_addr da, struct in6_addr sa6, struct in6_addr da6)
{
    unsigned short registered = 0;

    if(af == AF_INET)
    {
	if(cfg->mynet)
	{
            struct my_network *p;
            p = my_ipv4_net;
            // vegigmegyunk az elmentett ipcimeken
            while(p)
            {
        	// ha egyezik a kuldo ipcimmel, akkor feldolgozzuk
                if(p->addr.s_addr == sa.s_addr)
                {
                    register_packet(AF_INET, p, packetlen, protonum, "sa");
                    registered = 1;
                }
                // ha egyezik a fogado ipcimmel, akkor feldolgozzuk
                if(p->addr.s_addr == da.s_addr)
                {
                    register_packet(AF_INET, p, packetlen, protonum, "da");
                    registered = 1;
                }
                p = p->next;
            }
            // ha nem talalta meg a listaban, akkor felvesszuk, persze csak akkor, ha a tartomanyokba beletartozik
            if(registered == 0)
            {
		if(onnetlist(sa))
		{
		    add_ipv4_addr(sa, packetlen, protonum, "sa");
		}
		if(onnetlist(da))
		{
		    add_ipv4_addr(da, packetlen, protonum, "da");
		}
            }
        }
    }
    else if(af == AF_INET6)
    {
	int i, count;
	
	if(cfg->mynet6)
	{
            struct my_network6 *p;
            p = my_ipv6_net;
            // vegigmegyunk az elmentett ipcimeken
            while(p)
            {
        	// ha egyezik a kuldo ipcimmel, akkor feldolgozzuk
        	for(i = 0, count = 0; i < sizeof(sa6.s6_addr); i++)
		{
		    if(p->addr.s6_addr[i] == sa6.s6_addr[i])
		    {
			count++;
		    }
		}
		if(count == sizeof(sa6.s6_addr))
		{
                    register_packet(AF_INET6, p, packetlen, protonum, "sa");
                    registered = 1;
		}
		// ha egyezik a fogado ipcimmel, akkor feldolgozzuk
		for(i = 0, count = 0; i < sizeof(da6.s6_addr); i++)
		{
		    if(p->addr.s6_addr[i] == da6.s6_addr[i])
		    {
			count++;
		    }
		}
		if(count == sizeof(da6.s6_addr))
		{
                    register_packet(AF_INET6, p, packetlen, protonum, "da");
                    registered = 1;
		}
                p = p->next;
            }
            // ha nem talalta meg a listaban, akkor felvesszuk, persze csak akkor, ha a tartomanyokba beletartozik
            if(registered == 0)
            {
		if(onnetlist6(sa6))
		{
		    add_ipv6_addr(sa6, packetlen, protonum, "sa");
		}
		if(onnetlist6(da6))
		{
		    add_ipv6_addr(da6, packetlen, protonum, "da");
		}
            }
        }
    }
}

void *get_data_from_queue()
{  
    int af;
    int protonum;
    int packetlen;
    struct in_addr sa, da;
    struct in6_addr sa6, da6;
    struct node *tmp;
      
    while(1)
    {
	af = 0;
	protonum = 0;
	packetlen = 0;
	tmp = fifo->head;
	if(tmp != NULL)
	{
	    // kiolvassuk az adatokat a pufferbol
	    af = tmp->af;
	    protonum = tmp->protonum;
	    packetlen = tmp->packetlen;
	    sa = tmp->sa;
	    da = tmp->da;
	    sa6 = tmp->sa6;
	    da6 = tmp->da6;
	    // tovabb leptetjuk a fejet es felszabaditjuk a memoriat
	    fifo->head = tmp->next;
	    free(tmp);
	    
	    // tovabb adjuk az adatokat feldolgozasra
	    handle_frame(af, protonum, packetlen, sa, da, sa6, da6);
	}
	else
	{
	    // ha nincs adat a pufferben akkor var 5 masodpercet es ujraprobalja
	    //syslog(LOG_DEBUG, "get_data_from_queue: FIFO is empty\n");
	    sleep(5);
	}
    }
  
    pthread_exit(NULL);
}

// a csomag adatait egy pufferben fogjuk tarolni addig amig nem kerul feldolgozasra
void send_data_to_queue(int af, unsigned char *buffer, int size)
{
    if(af == AF_INET)
    {
	int i;
	struct iphdr *iph = (struct iphdr *)buffer;
	struct node *tmp;
	
	tmp = malloc(sizeof(struct node));
	if(tmp != NULL)
	{	  
	    tmp->af = af;
	    tmp->protonum = iph->protocol;
	    tmp->packetlen = ntohs(iph->tot_len);
	    tmp->sa.s_addr = iph->saddr;
	    tmp->da.s_addr = iph->daddr;
	    //tmp->sa6.s6_addr[16] = { 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0 };
	    for(i = 0; i < sizeof(tmp->sa6.s6_addr); i++)
	    {
		tmp->sa6.s6_addr[i] = 0;
	    }
	    //tmp->da6.s6_addr[16] = { 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0 };
	    for(i = 0; i < sizeof(tmp->da6.s6_addr); i++)
	    {
		tmp->da6.s6_addr[i] = 0;
	    }
	    tmp->next = NULL;
	    // ha ures a fifo
	    if(fifo->head == NULL)
	    {
		// azonos memoria cimre allitjuk a ket listat
		fifo->head = fifo->tail = tmp;
	    }
	    else // ha mar van adat a fifoban
	    {
		// hozzaadjuk a fifohoz az uj adatokat
		// mindket lista (head, tail) egyszerre novekszik
		fifo->tail->next = tmp;
		// majd a tailt visszaallitjuk az utolso elemre
		// igy mindig a lista vegere fog kerulni az uj adat
		fifo->tail = tmp;
	    }
	}
	else
	{
	    syslog(LOG_DEBUG, "send_data_to_queue: FIFO is full\n");
	    sleep(1);
	}
    }
    else if(af == AF_INET6)
    {
	int protonum;
	int i, count;
	struct ip6_hdr *ip6h = (struct ip6_hdr *)buffer;
	struct node *tmp;
	
	protonum = ip6h->ip6_nxt;

        unsigned char *cp = (unsigned char *)ip6h;
        int hlen = sizeof(*ip6h);

        cp += hlen;
        unsigned short int noexthdr = 0;
        // ha az ipv6-os csomag tobb kiterjesztett fejleccel is rendelkezik, akkor lehamozzuk rola
        while(noexthdr != 1)
        {
            switch(protonum)
            {
                case IPPROTO_HOPOPTS: // IPv6 Hop-by-Hop options (protocol number: 0)
            	    hlen = (((struct ip6_hbh *)cp)->ip6h_len+1) << 3;
                    protonum = ((struct ip6_hbh *)cp)->ip6h_nxt;
                    cp += hlen;
            	    break;
                case IPPROTO_IPV6: // IPv6 header (protocol number: 41)
                    hlen = (((struct ip6_ext *)cp)->ip6e_len + 1) << 3;
                    protonum = ((struct ip6_ext *)cp)->ip6e_nxt;
                    cp += hlen;
                    break;
                case IPPROTO_ROUTING: // IPv6 routing header (protocol number: 43)
                    hlen = (((struct ip6_rthdr *)cp)->ip6r_len+1) << 3;
                    protonum = ((struct ip6_rthdr *)cp)->ip6r_nxt;
                    cp += hlen;
                    break;
                case IPPROTO_FRAGMENT: // IPv6 fragmentation header (protocol number: 44)
                    hlen = sizeof(struct ip6_frag);
                    protonum = ((struct ip6_frag *)cp)->ip6f_nxt;
                    cp += hlen;
            	    break;
                case IPPROTO_ESP: // encapsulating security payload (protocol number: 50)
                    hlen = (((struct ip6_ext *)cp)->ip6e_len + 1) << 3;
                    protonum = ((struct ip6_ext *)cp)->ip6e_nxt;
                    cp += hlen;
                    break;
                case IPPROTO_AH: // authentication header (protocol number: 51)
                    //hlen = (((struct ah *)cp)->ah_len+2) << 2; // or
                    hlen = (((struct ip6_ext *)cp)->ip6e_len + 2) << 2;
                    //protonum = ((struct ah *)cp)->ah_nxt; // or
                    protonum = ((struct ip6_ext *)cp)->ip6e_nxt;
                    cp += hlen;
                    break;
                case IPPROTO_NONE: // IPv6 no next header (protocol number: 59)
                    noexthdr = 1;
                    break;
                case IPPROTO_DSTOPTS: // IPv6 destination options (protocol number: 60)
            	    hlen = (((struct ip6_dest *)cp)->ip6d_len+1) << 3;
                    protonum = ((struct ip6_dest *)cp)->ip6d_nxt;
                    cp += hlen;
                    break;
                case IPPROTO_MH: // IPv6 mobiliti header (protocol number: 135)
                    hlen = (((struct ip6_ext *)cp)->ip6e_len + 1) << 3;
                    protonum = ((struct ip6_ext *)cp)->ip6e_nxt;
                    cp += hlen;
                    break;
                default:
                    noexthdr = 1;
                    break;
            }
        }
        
	tmp = malloc(sizeof(struct node));
	if(tmp != NULL)
	{
	    tmp->af = af;
	    tmp->protonum = protonum;
	    tmp->packetlen = ntohs(ip6h->ip6_plen);
	    tmp->sa.s_addr = 0;
	    tmp->da.s_addr = 0;
	    tmp->sa6 = ip6h->ip6_src;
	    tmp->da6 = ip6h->ip6_dst;
	    tmp->next = NULL;
	    // ha ures a fifo
	    if(fifo->head == NULL)
	    {
		// azonos memoria cimre allitjuk a ket listat
		fifo->head = fifo->tail = tmp;
	    }
	    else // ha mar van adat a fifoban
	    {
		// hozzaadjuk a fifohoz az uj adatokat
		// mindket lista (head, tail) egyszerre novekszik
		fifo->tail->next = tmp;
		// majd a tailt visszaallitjuk az utolso elemre
		// igy mindig a lista vegere fog kerulni az uj adat
		fifo->tail = tmp;
	    }
	}
	else
	{
	    syslog(LOG_DEBUG, "send_data_to_queue: FIFO is full\n");
	    sleep(1);
	}
    }
}

void process_packet(u_char *args, const struct pcap_pkthdr *header, const u_char *packet)
{
    int size = header->len; // csomag fejlec hossza
    struct ether_header *ethh;

    ethh = (struct ether_header *)packet;

    // szortirozzuk a csomagokat tipus szerint
    if(ntohs(ethh->ether_type) == ETHERTYPE_IP) // ha IPv4
    {
    	send_data_to_queue(AF_INET, (unsigned char *)(packet + sizeof *ethh), size - sizeof ethh);
    }
    else if(ntohs(ethh->ether_type) == ETHERTYPE_IPV6) // ha IPv6
    {
    	send_data_to_queue(AF_INET6, (unsigned char *)(packet + sizeof *ethh), size - sizeof ethh);
    }
}

void *packet_loop()
{
    fd_set readmask;
    int pcap_fd;
    pcap_fd = pcap_fileno(pds);
    while(1)
    {
        FD_ZERO(&readmask);
        FD_SET(pcap_fd, &readmask);
        if(select(pcap_fd + 1, &readmask, NULL, NULL, NULL) > 0)
        {
            if(pcap_dispatch(pds, 1, process_packet, NULL) < 0)
            {
                syslog(LOG_ERR, "pcap_dispatch: %s\n", pcap_geterr(pds));
                err_quit();
                daemon_stop(0);
            }
        }
    }
    
    pthread_exit(NULL);
}

int do_pid_file(void)
{
    // return 1 ha letre lehet hozni a filet
    // return 0 ha a demon mar fut
    // ez lesz itten a pid fileunk
    FILE *f;
    if(access(cfg->pid_file, F_OK) == 0)
    {
        char buff[80];
        unsigned int pid;
        // a file mar letezik, ezert csak olvasasra nyitjuk meg
        f = fopen(cfg->pid_file, "r");
        if(f == NULL)
        {
    	    syslog(LOG_ERR, "do_pid_file: no such file or directory: %s\n", cfg->pid_file);
	    err_quit();
	    exit(1);
        }
        fgets(buff, sizeof(buff), f);
        // meg zarjuk, de elotte kimentettuk a pidet belole
        fclose(f);
        // konvertaljuk int-be
        pid = atoi(buff);
        syslog(LOG_INFO, "found pid-file with pid %d\n", pid);
        if(kill(pid, 0) == -1)
        {
            syslog(LOG_INFO, "process %d doesn't exist anymore\n", pid);
        }
        else
        {
            syslog(LOG_INFO, "process %d is still running.\n", pid);
            return 0;
        }
    }
    // most megnyitjuk pid filet irasra
    f = fopen(cfg->pid_file, "w");
    if(f == NULL)
    {
        syslog(LOG_ERR, "do_pid_file: no such file or directory: %s\n", cfg->pid_file);
        err_quit();
        exit(1);
    }
    // es beleirjuk a mi pidunket
    fprintf(f, "%d\n", (int) getpid());
    // bezarjuk
    fclose(f);
    return 1;
}

int daemon_start(void)
{
    unsigned short int i;
    pid_t pid;
    // daemonizalas indul
    if((pid = fork()) < 0)
    {
        // ha nem sikerul a forkolas akkor kilepes
        return(-1);
    }
    else if(pid != 0)
    {
        // ha megis akkor a child procesz kilep
        exit(0);
    }
    closelog();
    for(i = 0; i < FD_SETSIZE; i++)
    {
        // bezarunk egy halom file leirot, ilyen stdin, stdout szerusegeket
        close(i);
    }
    // mi vagyunk a session leaderek
    setsid();
    return 0;
}

void daemon_stop(int sig)
{
    // toroljuk a pid filet
    unlink(cfg->pid_file);
    syslog(LOG_INFO, "Myntcd daemon terminating (%d)\n",sig);
    // befejezzunk a csomaglopast
    exit_capture();
    if (sig != 0)
    {
        // kiirjuk az adatokat meg egyszer utoljara
        write_data(cfg, start_log, time(NULL));
    }
    // felszabaditjuk a lefoglalt memoria teruletet
    free_mem_cfg(cfg);
    // logolast is abbahagyjuk
    closelog();
    // kilepunk
    exit(1);
}

void write_data(struct config *conf, time_t stime, time_t ltime)
{
    FILE *df;
    char dfname[255], tf[255];

    if (0 == strftime(tf, sizeof(tf), "%Y_%m_%d_%H_%M_%S", localtime(&ltime)))
    {
        sprintf(dfname, "%s/%s.dat", conf->dir, conf->prefix);
    }
    else
    {
        sprintf(dfname, "%s/%s.%s.dat", conf->dir, conf->prefix, tf);
    }
    df = fopen(dfname, "w");
    if(df == NULL)
    {
        syslog(LOG_ERR, "write_data: no such file or directory\n");
        err_quit();
        exit(1);
    }
    fprintf(df, "%d %d\n", (int)stime,(int)ltime);
    start_log = time(NULL);

    if(conf->mynet)
    {
	static char straddr[INET_ADDRSTRLEN];
	struct my_network *p, *tmp;
	int delhead = 0;
	
	p = my_ipv4_net;
	while(p)
	{
	    if(0 != (p->in.t_count + p->in.u_count + p->in.i_count + p->in.o_count + p->out.t_count + p->out.u_count + p->out.i_count + p->out.o_count))
	    {
		inet_ntop(AF_INET, &p->addr, straddr, sizeof(straddr));
        	//write ip
        	fprintf(df,"%s", straddr);
        	//write tcp traffic
        	fprintf(df," %li %li", p->in.t_count, p->out.t_count);
        	fprintf(df," %qd %qd", p->in.t_bytes, p->out.t_bytes);
        	//write udp traffic
        	fprintf(df," %li %li", p->in.u_count, p->out.u_count);
        	fprintf(df," %qd %qd", p->in.u_bytes, p->out.u_bytes);
        	//write icmp traffic
        	fprintf(df," %li %li", p->in.i_count, p->out.i_count);
        	fprintf(df," %qd %qd", p->in.i_bytes, p->out.i_bytes);
        	//write other traffic
        	fprintf(df," %li %li", p->in.o_count, p->out.o_count);
        	fprintf(df," %qd %qd", p->in.o_bytes, p->out.o_bytes);
        	fprintf(df,"\n");
		
		clear_count(AF_INET, p);
	    }
	    else // ha nem volt forgalom egy adott ip cimen, akkor kitoroljuk a listarol
	    {
		// ha az elso (head) elem a listaban
		if(p == my_ipv4_net)
		{
		    // az elso elem a lista masodik eleme lesz
		    my_ipv4_net = p->next;
		    // felszabaditja a memoriat
		    free(p);
		    // majd biztonsag kedveert visszamasoljuk p-be a listankat
		    // ha a struct-nal nem az utolso elem a *next akkor biztos elszall e nelkul
		    p = my_ipv4_net;
		    // nem leptetjuk a listat tovabb, mert akkor kimarad egy elem
		    delhead = 1;
		}
		else // barmelyik masik eleme a listanak
		{
		    // atleptetjuk a kovetkezo elemre
		    tmp->next = p->next;
		    // felszabaditjuk a memoriat
		    free(p);
		    // ua. mint a masiknal
		    p = tmp;
		}
	    }
	    // ha az elso elemet toroltuk akkor a tmp is megkapja a valtozasokat
	    tmp = p;
	    // ha nem a fejet toroltuk
	    if(delhead == 0)
	    {
		p = p->next;
	    }
	    else
	    {
		delhead = 0;
	    }
	}
    }
    if(conf->mynet6)
    {
	static char straddr6[INET6_ADDRSTRLEN];
	struct my_network6 *p, *tmp;
	int delhead = 0;
	
	p = my_ipv6_net;
	while(p)
	{
	    if(0 != (p->in.t_count + p->in.u_count + p->in.i_count + p->in.o_count + p->out.t_count + p->out.u_count + p->out.i_count + p->out.o_count))
	    {
		inet_ntop(AF_INET6, &p->addr, straddr6, sizeof(straddr6));
        	//write ip
        	fprintf(df,"%s", straddr6);
        	//write tcp traffic
        	fprintf(df," %li %li", p->in.t_count, p->out.t_count);
        	fprintf(df," %qd %qd", p->in.t_bytes, p->out.t_bytes);
        	//write udp traffic
        	fprintf(df," %li %li", p->in.u_count, p->out.u_count);
        	fprintf(df," %qd %qd", p->in.u_bytes, p->out.u_bytes);
        	//write icmp traffic
        	fprintf(df," %li %li", p->in.i_count, p->out.i_count);
        	fprintf(df," %qd %qd", p->in.i_bytes, p->out.i_bytes);
        	//write other traffic
        	fprintf(df," %li %li", p->in.o_count, p->out.o_count);
        	fprintf(df," %qd %qd", p->in.o_bytes, p->out.o_bytes);
        	fprintf(df,"\n");
		
		clear_count(AF_INET6, p);
	    }
	    else // ha nem volt forgalom egy adott ip cimen, akkor kitoroljuk a listarol
	    {
		// ha az elso (head) elem a listaban
		if(p == my_ipv6_net)
		{
		    // az elso elem a lista masodik eleme lesz
		    my_ipv6_net = p->next;
		    // felszabaditja a memoriat
		    free(p);
		    // majd biztonsag kedveert visszamasoljuk p-be a listankat
		    // ha a struct-nal nem az utolso elem a *next akkor biztos elszall e nelkul
		    p = my_ipv6_net;
		    // nem leptetjuk a listat tovabb, mert akkor kimarad egy elem
		    delhead = 1;
		}
		else // barmelyik masik eleme a listanak
		{
		    // atleptetjuk a kovetkezo elemre
		    tmp->next = p->next;
		    // felszabaditjuk a memoriat
		    free(p);
		    // ua. mint a masiknal
		    p = tmp;
		}
	    }
	    // ha az elso elemet toroltuk akkor a tmp is megkapja a valtozasokat
	    tmp = p;
	    // ha nem a fejet toroltuk
	    if(delhead == 0)
	    {
		p = p->next;
	    }
	    else
	    {
		delhead = 0;
	    }
	}
    }
    fflush(df);
    fclose(df);
}

void alarm_handler(int sig)
{
    static time_t last_check = 0;
    now++;
    if((now - last_check) > 60)
    {
        // ha tobb mint 1 perc telt el a legutobbi ellenorzes ota akkor
        time_t nnow;
        // megujitjuk az aktualis idot
        nnow = time(NULL);
        if(nnow != now)
        {
            if((abs(nnow - now) > 2))
            {
                // de a legutobbi "most" ota meg nem telt el 2 mp
                syslog(LOG_INFO, "got signal  %d, ignoring\n",sig);
            }
            // a most az legyen most
            now = nnow;
        }
        last_check = now;
    }
    if(!(now % cfg->save_interval) && (cfg->save_interval != 0))
    {
        // ha itt az ido, akkor adatokat irjuk ki
        write_data(cfg, start_log, now);
    }
    // 1 mp mulva probald ujra
    alarm(1);
}

#define SETSIG(sig, fun, fla)   sa.sa_handler = fun; \
                                sa.sa_flags = fla; \
                                sigaction(sig, &sa, NULL);
void signal_ignore(int sig)
{
    // bejovo signalokat figyelmen kivul hagyjuk
    syslog(LOG_INFO, "got signal  %d, ignoring\n",sig);
}

void signal_setup(void)
{
    unsigned short int i;
    struct sigaction sa;
    for (i = 1; i < NSIG; ++i)
    {
        signal(i, signal_ignore);
    }
    // a program leallitasat kero szignalokat atiranyitjuk magunkhoz
    SETSIG(SIGINT, daemon_stop, 0);
    SETSIG(SIGKILL, daemon_stop, 0);
    SETSIG(SIGTERM, daemon_stop, 0);
    SETSIG(SIGSEGV, daemon_stop, 0);
    // ez meg a belso oraert felelos meg a fileokba irasert
    SETSIG(SIGALRM, alarm_handler, 0);
    SETSIG(SIGUSR1, reload_config, 0);

    // ez meg itt arra figyelmeztet ha a gyermek processunk kilepett
//  SETSIG(SIGCHLD, child_finished, 0);
}

void usage(char *progname)
{
    fprintf(stderr, "Usage: %s [-d] [-c filename]\n\n", progname);
    fprintf(stderr, "\t-c\tSpecify alternative configuration file\n");
    fprintf(stderr, "\t-d\tNot start like a daemon\n\n");
}

void process_options(int argc, char **argv)
{
    int c;
    char *progname;
    // eltesszuk a programunk binarisanak nevet kesobbi felhasznalasra
    progname = argv[0];
    fname = strdup(CONFIG);

    while ((c = getopt( argc, argv, "c:d" )) != -1)
    {
    // ertelmezzuk a command line opciokat
	switch (c)
	{
    	    case 'c':
        	// modositsuk a configfilet helyet
        	free(fname);
        	fname = strdup(optarg);
		break;
    	    case 'd':
        	// nem demonkent akarjuk futtatni a programot
        	daem = 0;
        	break;
    	    case '?':
    	    default:
        	// help
        	usage(progname);
        	exit(1);
    	}
    }

    argc -= optind;
    argv += optind;

    if (argc > 1)
    {
        // megmondjuk hogy hogy kell hasznalni a programot
        usage(progname);
        exit(1);
    }
}

int main(int argc, char **argv)
{
    // system logger felkeszitese
    openlog("myntcd", 0, LOG_DAEMON);
    // effective user id megkaparintasa
    if(geteuid() != 0)
    {
        syslog(LOG_ERR, "must be superuser to run myntcd\n");
        // ha nem rootkent van inditva akkor kilepunk
        exit(1);
    }
    // parancssori parameterek feldolgozasa
    process_options(argc, argv);
    // konfiguracios file beolvasasa es a cfg strukturaba bepakolasa
    cfg = read_config(fname);

    if(cfg != NULL)
    {
	// ha a konfiguralas sikeres volt
        if(daem)
        {
            // ha daemonkent fut a cuccos
            if(daemon_start() != -1)
            {
                // ha a daemonunk szepen leforkolodott akkor...
                openlog("myntcd", 0, LOG_DAEMON);
                syslog(LOG_INFO, "Myntcd daemon forked\n");
            }
            else
            {
                syslog(LOG_ERR, "couldn't fork: %m\n");
                syslog(LOG_INFO, "Myntcd daemon aborting\n");
                err_quit();
                exit(1);
            }
        }
        else
        {
            // ha pedig konzol modban
            syslog(LOG_INFO, "Start myntcd console mode. %s\n", fname);
        }
        if(!do_pid_file())
        {
            syslog(LOG_ERR, "daemon already running or stale pid-file\n");
            err_quit();
            exit(1);
        }
        // ellopkodjuk a signal handlereket
        signal_setup();
        // aztan meg a csomagokat is ellopjuk
        init_capture();
        // es elkezdjuk az accountingot is
        do_acct();
        // aztan kilepunk belole
        exit_capture();
	// felszabaditjuk a lefoglalt memoria teruletet
	free_mem_cfg(cfg);

        return 0;
    }
    else
    {
        // valami hiba tortent a config fajl beolvasasakor
        syslog(LOG_INFO, "Fault during the reading of the config file: %s\n", fname);
        err_quit();
    }

    return 1;
}
