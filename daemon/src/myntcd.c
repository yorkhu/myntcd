//cc -D'CONF_DIR="/usr/local/etc/myntcd/"' -D'CONF_FILE="myntcd.conf"' -O2 -lpcap -lpthread myntcd.c -o myntcd
#ifdef HAVE_CONFIG_H
#include <config.h>
#endif
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <signal.h>
#include <syslog.h>

// #include <netdb.h>
#include <net/ethernet.h>
#include <ctype.h>
#include <net/if.h>
//#include <sys/socket.h>
//#include <sys/ioctl.h>
//#include <linux/if_ether.h>
//#include <asm/types.h>
//#include <netinet/ip.h>
#include <netinet/udp.h>
#include <netinet/tcp.h>
#include <netinet/ip_icmp.h>
#include <errno.h>
//#include <ctype.h>
//#include <time.h>

#include <sys/wait.h>

#include <pcap.h>
#include <pthread.h>

#define HASH_SIZE 4096

#if !defined(CONF_DIR)
#define CONF_DIR "/usr/local/etc/myntcd/"
#endif

#if !defined(CONF_FILE)
#define CONF_FILE "myntcd.conf"
#endif

#define CONFIG CONF_DIR"/"CONF_FILE

#define PCAP_SNAPLEN 128
#define PCAP_TMOUT 1000

//STRUCT
struct config {
	char *pid_file;
	char *dir;
	char *prefix;
	unsigned short int sniff;
	unsigned short int save_interval; // in seconds
	struct promisc_device *promisc;
	struct headerdat *headers;
	struct my_network8 *mynet8;
	struct my_network16 *mynet16;
	struct my_network24 *mynet24;
	struct my_network32 *mynet32;
};

struct ipdata {
	unsigned long t_count;
	uint64_t t_bytes;
	unsigned long u_count;
	uint64_t u_bytes;
	unsigned long i_count;
	uint64_t i_bytes;
	unsigned long o_count;
	uint64_t o_bytes;
};

struct my_net_header {
	unsigned long addr,mask;
	struct my_net_header *next;
};

struct my_network8 {
	unsigned long addr,mask;
	struct my_network8 *next;
	struct ipdata in[256][256][256];
	struct ipdata out[256][256][256];
};

struct my_network16 {
	unsigned long addr,mask;
	struct my_network16 *next;
	struct ipdata in[256][256];
	struct ipdata out[256][256];
};

struct my_network24 {
	unsigned long addr,mask;
	struct my_network24 *next;
	struct ipdata in[256];
	struct ipdata out[256];
};

struct my_network32 {
	unsigned long addr,mask;
	struct my_network32 *next;
	struct ipdata in;
	struct ipdata out;
};

struct promisc_device {
	char *name; // name (e.g. eth0)
	unsigned short int reset; // do we have to reset it on exit ?
	struct ifreq oldifr; // old settings
};

struct headerdat {
	char *name;
	unsigned short int l;
	unsigned short int offset;
	unsigned short int type;
};

//VARIABLE
char *rcs_revision_main_c = "$Revision: 1.4 $";
char *progname;
char *fname = NULL;
unsigned int daem = 1;


struct headerdat headers;
struct my_network8 *mynet_w8 = NULL, *mynet_n8 = NULL;
struct my_network16 *mynet_w16 = NULL, *mynet_n16 = NULL;
struct my_network24 *mynet_w24 = NULL, *mynet_n24 = NULL;
struct my_network32 *mynet_w32 = NULL, *mynet_n32 = NULL;

unsigned short int IP_ICMP = 0;
unsigned short int IP_TCP = 0;
unsigned short int IP_UDP = 0;

struct config *cfg;
volatile int running = 0;

volatile time_t start_log, now; // current time

char perrbuff[PCAP_ERRBUF_SIZE];
pcap_t *pds; // array of packet descriptors per interface
pthread_mutex_t pt_lock;
pthread_t pt;
int *taskids; // max interfaces (each interface - thread)

// FUNCTION LIST

char *intoa(unsigned long addr);
void err_quit(void);
struct config *read_config(char *fname);
void init_cfg (struct config *init_cfg);
void exit_cfg (struct config *exit_cfg);
void reload_config(int sig);
void init_capture(void);
void exit_capture(void);
void do_acct(void);
void clear_count(void *myn);
void register_packet_tcp(unsigned long int src,unsigned long int dst, int size);
void register_packet_udp(unsigned long int src,unsigned long int dst, int size);
void register_packet_icmp(unsigned long int src,unsigned long int dst, int size);
void register_packet_other(unsigned long int src,unsigned long int dst, int size);
void handle_frame (unsigned char buf[], int length);
void do_packet(u_char *usr, const struct pcap_pkthdr *h, const u_char *p);
void *packet_loop(void *threadid);
int do_pid_file(void);
int daemon_start(void);
void daemon_stop(int sig);
void write_data(struct config *conf, time_t stime, time_t ltime);
void alarm_handler(int sig);
void signal_ignore(int sig);
void signal_setup(void);
void usage(void);
void process_options(int argc, char *argv[]);


// FUNCTIONS

char *intoa(unsigned long addr) {
	static char buff[18];
	char *p = (char *) &addr;
	sprintf(buff, "%d.%d.%d.%d",(p[0] & 255), (p[1] & 255), (p[2] & 255), (p[3] & 255));
	return(buff);
}

void err_quit(void) {
	// hibauzenet.
	fprintf(stderr, "myntcd didn't start. Read syslog.\n");
}

struct config *read_config(char *fname) {
	char buff[1024], *out_text=NULL;
	// config file
	FILE *cf; 
	unsigned short int line=0;
	// a konfiguraciot tarolo strukturara mutato pointer
	struct config *new_cfg = malloc(sizeof(struct config));
	// sikertelen memoriafoglalas eseten return null
	if(new_cfg  == NULL) return new_cfg ; 
	// feltoltjuk az ertekeket
	new_cfg -> pid_file = NULL; 
	new_cfg -> dir = NULL;
	new_cfg -> prefix = NULL;
	new_cfg -> sniff = 0;
	new_cfg -> save_interval = 0;
	new_cfg -> promisc = NULL;
	new_cfg -> headers = NULL;
	new_cfg -> mynet32 = NULL;
	new_cfg -> mynet24 = NULL;
	new_cfg -> mynet16 = NULL;
	new_cfg -> mynet8 = NULL;
	// konfigfilet olvasasra megnyit
	cf=fopen(fname,"r"); 
	if(cf == NULL) return NULL;

	while(fgets(buff,sizeof(buff),cf)) {
		// kitoroljuk a sorvege karaktereket
		char *cmt = strchr(buff,'\n'); 
		if(cmt) *cmt = '\0';
		line++;
		// kitoroljuk a kommenteket is
		cmt = strchr(buff,'#');
		if(cmt) *cmt = '\0';
		// kitoroljuk a vezeto whitespacekat is
		while(isspace(*buff)) {
			memmove(buff,buff+1,strlen(buff));
		}
		// kitoroljuk a sor vegi whitespaceket is
		cmt = strchr(buff,'\0');
		cmt --;
		while(isspace(*cmt)) {
			*cmt = '\0';
			cmt --;
		}
		// nem ures sorokat feldolgozzuk
		if(*buff) {
			char *kwd = buff; 
			char *value = buff + strcspn(buff," \t");
			*value++ = '\0';
			while(isspace(*value)) value++;
			if(strcasecmp(kwd, "device")==0) {
				struct promisc_device *tmp;
				syslog(LOG_DEBUG,"config: DEVICE: %s\n", strdup(value));

				tmp = malloc(sizeof(struct promisc_device));
				if(tmp != NULL) {
					tmp -> name  = strdup(value);
					tmp -> reset = 0;
					new_cfg -> promisc = tmp;
					syslog(LOG_DEBUG,"config: added promiscous device %s\n",new_cfg->promisc->name);
				}
			} else if(strcasecmp(kwd, "sniff")==0) {
				new_cfg->sniff = atoi(value);
				syslog(LOG_DEBUG,"config: sniff set to %d",new_cfg->sniff);
			} else if(strcasecmp(kwd, "headers")==0) {
				char *offset;
				char *type;
				struct headerdat *tmp;

				offset  = value + strcspn(value," \t");
				*offset++ = '\0';
				while(isspace(*offset)) offset++;

				type  = offset + strcspn(offset," \t");
				*type++ = '\0';
				while(isspace(*type)) type++;

				tmp = malloc(sizeof(struct headerdat));

				if(tmp != NULL) {
					tmp -> name = strdup(value);
					tmp -> l = strlen(value);
					tmp -> offset = atoi(offset);
					tmp -> type = atoi(type);
					new_cfg -> headers = tmp;
					syslog(LOG_DEBUG,"config: added headerinfo (%s:%d:%d)\n",tmp -> name, tmp -> offset, tmp -> type);
				}
			} else if(strcasecmp(kwd, "mynetwork")==0) {
				unsigned char c1,c2,c3,c4;
				unsigned char m1,m2,m3,m4;
				char *p;
				unsigned long ipaddr, mask;


				char **ip=(char**)malloc(sizeof(char*));
				int ipk=0;
				char *temp, *temp2;
				temp = temp2 = value;

				while (1)  {
					while (*temp!='\t' && *temp!=' ' && *temp!='\0') temp++;
					ip = (char**)realloc(ip,++ipk*sizeof(char*));
					ip[ipk-1] = (char*)strndup(temp2, temp-temp2);
					while(isspace(*temp)) temp++;
					temp2 = temp;
					if (temp-value==strlen(value)) break;
				}

				int i;
				for (i=0; i<ipk; i++) {
					mask=0xffffffff ;

					c1 = strtol(strtok(ip[i],"."),0,0);
					c2 = strtol(strtok(NULL,"."),0,0);
					c3 = strtol(strtok(NULL,"."),0,0);
					c4 = strtol(strtok(NULL,"/"),0,0);
	
					p=strtok(NULL,".");
					if (p!=NULL) {
						while(isspace(*p)) p++;
						m1 = strtol(p,0,0);
						if (strlen(p)<=2) {
							mask=mask>>(32-m1);
						} else {
							m2 = strtol(strtok(NULL,"."),0,0);
							m3 = strtol(strtok(NULL,"."),0,0);
							m4 = strtol(strtok(NULL,"."),0,0);
							mask = htonl((m1 << 24) | (m2 << 16) | (m3 << 8) | m4);
						}
					}
					ipaddr = htonl((c1 << 24) | (c2 << 16) | (c3 << 8) | c4);
					p=(char *) &mask;

					if (mask >= htonl(0xff000000)) {
						if (mask >= htonl(0xffff0000)) {
							if (mask >= htonl(0xffffff00)) {
								if (mask == htonl(0xffffffff)) {
									// /32 mask
									struct my_network32 *tmp;
									tmp = malloc(sizeof(struct my_network32));
									if(tmp != NULL) {
										tmp->addr = ipaddr;
										tmp->mask = mask;
										tmp->next = new_cfg->mynet32;
										new_cfg->mynet32 = tmp;
										out_text = strdup(intoa(tmp->addr));
										syslog(LOG_DEBUG,"config: added mynetwork %s/%s, %li/%li",out_text, intoa(tmp->mask), tmp->addr, tmp->mask);
									}
								} else {
									// /24 mask
									struct my_network24 *tmp;
									tmp = malloc(sizeof(struct my_network24));
									if(tmp != NULL) {
										tmp->addr = ipaddr;
										tmp->mask = mask;
										tmp->next = new_cfg->mynet24;
										new_cfg->mynet24 = tmp;
										out_text = strdup(intoa(tmp->addr));
										syslog(LOG_DEBUG,"config: added mynetwork %s/%s, %li/%li",out_text, intoa(tmp->mask), tmp->addr, tmp->mask);
									}
								}
							} else {
								// /16 mask
								struct my_network16 *tmp;
								tmp = malloc(sizeof(struct my_network16));
								if(tmp != NULL) {
									tmp->addr = ipaddr;
									tmp->mask = mask;
									tmp->next = new_cfg->mynet16;
									new_cfg->mynet16 = tmp;
									out_text = strdup(intoa(tmp->addr));
									syslog(LOG_DEBUG,"config: added mynetwork %s/%s, %li/%li",out_text, intoa(tmp->mask), tmp->addr, tmp->mask);
								}
							}
						} else {
							// /8 mask
							struct my_network8 *tmp;
							tmp = malloc(sizeof(struct my_network8));
							if(tmp != NULL) {
								tmp->addr = ipaddr;
								tmp->mask = mask;
								tmp->next = new_cfg->mynet8;
								new_cfg->mynet8 = tmp;
								out_text = strdup(intoa(tmp->addr));
								syslog(LOG_DEBUG,"config: added mynetwork %s/%s, %li/%li",out_text, intoa(tmp->mask), tmp->addr, tmp->mask);
							}
						}
					}
				}
			} else if(strcasecmp(kwd, "save_interval")==0) {
				new_cfg -> save_interval = atoi(value);
				syslog(LOG_DEBUG,"config: set save interval: %d\n",new_cfg -> save_interval);
			} else if(strcasecmp(kwd, "pid")==0) {
				new_cfg->pid_file = strdup(value);
				syslog(LOG_DEBUG,"config: set pid filename to %s\n",new_cfg->pid_file);
			} else if(strcasecmp(kwd, "dir")==0) {
				new_cfg->dir = strdup(value);
				syslog(LOG_DEBUG,"config: set data directory to %s\n",new_cfg->dir);
			} else if(strcasecmp(kwd, "prefix")==0) {
				new_cfg->prefix = strdup(value);
				syslog(LOG_DEBUG,"config: set data filename prefix to %s\n",new_cfg->prefix);
			}
		}
	}
    
	if(new_cfg -> promisc == NULL) {
		syslog(LOG_ERR, "config file: no device given\n");
		err_quit();
		return NULL;
	}

	if(new_cfg->headers == NULL) {
		syslog(LOG_ERR, "config file: no header information given\n");
		err_quit();
		return NULL;
	}
    
	if((new_cfg->mynet32 == NULL) && (new_cfg->mynet24 == NULL) && (new_cfg->mynet16 == NULL) && (new_cfg->mynet8 == NULL)) {
		syslog(LOG_ERR, "config file: no mynetwork given\n");
		err_quit();
		return NULL;
	}

	if(new_cfg->prefix == NULL) {
		syslog(LOG_ERR, "config file: no prefix given\n");
		err_quit();
		return NULL;
	}

	if(new_cfg->pid_file == NULL) {
		syslog(LOG_ERR, "config file: no pid file given\n");
		err_quit();
		return NULL;
	}
    
	fclose(cf);
	return new_cfg;
}

void init_cfg (struct config *init_cfg) {
	if (init_cfg->mynet8) {
		if ((mynet_w8 == NULL) || (mynet_n8 == NULL)) {
			if (mynet_n8 == NULL) {
				mynet_n8 = malloc(sizeof(struct my_network8));
				if(mynet_n8 != NULL) {
					mynet_n8->addr = 0;
					mynet_n8->mask = htonl(0xff000000);
					mynet_n8->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
			if (mynet_w8 == NULL) {
				mynet_w8 = malloc(sizeof(struct my_network8));
				if(mynet_w8 != NULL) {
					mynet_w8->addr = 0;
					mynet_w8->mask = htonl(0xff000000);
					mynet_w8->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
		}
		clear_count(mynet_w8);
		clear_count(mynet_n8);
		clear_count(init_cfg->mynet8);
	}
	if (init_cfg->mynet16) {
		if ((mynet_w16 == NULL) || (mynet_n16 == NULL)) {
			if (mynet_n16 == NULL) {
				mynet_n16 = malloc(sizeof(struct my_network16));
				if(mynet_n16 != NULL) {
					mynet_n16->addr = 0;
					mynet_n16->mask = htonl(0xffff0000);
					mynet_n16->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
			if (mynet_w16 == NULL) {
				mynet_w16 = malloc(sizeof(struct my_network16));
				if(mynet_w16 != NULL) {
					mynet_w16->addr = 0;
					mynet_w16->mask = htonl(0xffff0000);
					mynet_w16->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
		}
		clear_count(mynet_w16);
		clear_count(mynet_n16);
		clear_count(init_cfg->mynet16);
	}
	if (init_cfg->mynet24) {
		if ((mynet_w24 == NULL) || (mynet_n24 == NULL)) {
			if (mynet_n24 == NULL) {
				mynet_n24 = malloc(sizeof(struct my_network24));
				if(mynet_n24 != NULL) {
					mynet_n24->addr = 0;
					mynet_n24->mask = htonl(0xffffff00);
					mynet_n24->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
			if (mynet_w24 == NULL) {
				mynet_w24 = malloc(sizeof(struct my_network24));
				if(mynet_w24 != NULL) {
					mynet_w24->addr = 0;
					mynet_w24->mask = htonl(0xffffff00);
					mynet_w24->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
		}
		clear_count(mynet_w24);
		clear_count(mynet_n24);
		clear_count(init_cfg->mynet24);
	}
	if (init_cfg->mynet32) {
		if ((mynet_w32 == NULL) || (mynet_n32 == NULL)) {
			if (mynet_n32 == NULL) {
				mynet_n32 = malloc(sizeof(struct my_network32));
				if(mynet_n32 != NULL) {
					mynet_n32->addr = 0;
					mynet_n32->mask = htonl(0xffffffff);
					mynet_n32->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
			if (mynet_w32 == NULL) {
				mynet_w32 = malloc(sizeof(struct my_network32));
				if(mynet_w32 != NULL) {
					mynet_w32->addr = 0;
					mynet_w32->mask = htonl(0xffffffff);
					mynet_w32->next = NULL;
				} else {
					syslog(LOG_ERR, "QUIT!!!\n");
					daemon_stop(0);
				}
			}
		}
		clear_count(mynet_w32);
		clear_count(mynet_n32);
		clear_count(init_cfg->mynet32);
	}
}

void exit_cfg (struct config *exit_cfg) {
	if (exit_cfg) {
		if (exit_cfg->mynet8) {
			struct my_network8 *p1, *p2;
			for(p1=exit_cfg->mynet8;p1;p1=p2) {
				p2 = p1->next;
				free(p1);
			}
		}
		if (exit_cfg->mynet16) {
			struct my_network16 *p1, *p2;
			for(p1=exit_cfg->mynet16;p1;p1=p2) {
				p2 = p1->next;
				free(p1);
			}
		}
		if (exit_cfg->mynet24) {
			struct my_network24 *p1, *p2;
			for(p1=exit_cfg->mynet24;p1;p1=p2) {
				p2 = p1->next;
				free(p1);
			}
		}
		if (exit_cfg->mynet32) {
			struct my_network32 *p1, *p2;
			for(p1=exit_cfg->mynet32;p1;p1=p2) {
				p2 = p1->next;
				free(p1);
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
	if (cfg) {
		if (cfg->mynet8==NULL) {
			free(mynet_n8);
			free(mynet_w8);
		}
		if (cfg->mynet16==NULL) {
			free(mynet_n16);
			free(mynet_w16);
		}
		if (cfg->mynet24==NULL) {
			free(mynet_n24);
			free(mynet_w24);
		}
		if (cfg->mynet32==NULL) {
			free(mynet_n32);
			free(mynet_w32);
		}
	}
}

void reload_config(int sig) {
	struct config *old_cfg, *new_cfg;
	// megmondjuk a sysolognak, hogy ujratoltodunk
	syslog(LOG_INFO,"Re-Load config file!\n");
	// a jelenlegi konfigot attoltjuk old_cfg-be
	old_cfg = cfg;
	// az ujat meg beolvassuk a configfilebol
	new_cfg = read_config(fname);
	// ervenyesitjuk
	init_cfg(new_cfg);
	// leallitjuk a csomaglopast
	exit_capture();
	// frissitjuk az aktualis konfigot
	cfg=new_cfg;
	// es folytatjuk a lopkodast
	init_capture();
	// kiirjuk az adatainkat is
	write_data(old_cfg, start_log, time(NULL));
	// felszabaditjuk a regi konfiguracionak foglalt helyet
	exit_cfg(old_cfg);
}

void init_capture(void) {
	struct promisc_device *p;
	p = cfg -> promisc;
	// megnyitjuk az interfacenket
	if (p!=NULL) {
		// promisc mod
		pds = pcap_open_live(p -> name,PCAP_SNAPLEN, cfg->sniff, PCAP_TMOUT, perrbuff);
		if(pds == NULL) {
			// ha nem megy, azt tudatjuk
			syslog(LOG_ERR, "can't pcap_open_live: %s\n", perrbuff);
			// majd kilepunk
			daemon_stop(0);
		}
	}
}

void exit_capture(void) {
	if(pds != NULL) {
		// koszonjuk szepen nem kerunk tobb csomagot
		pcap_close(pds);
		// ez meg itten a jo oreg packet interfacenk leallitasa
		pds = NULL;
	}
}

void do_acct(void) {
	struct promisc_device *p;
	p = cfg -> promisc;

	start_log = now = time(NULL);
	alarm(1);
	running=1;

	// creating pthread mutex
	pthread_mutex_init(&pt_lock, NULL);
	// each interface get its own thread

	if (p!=NULL) {
		taskids = (int *) malloc(sizeof(int));
		*taskids = 1;
		// create thread and pass interface number as argument
		if(pthread_create(&pt, NULL, packet_loop,taskids)) {
			syslog(LOG_DEBUG, "pthread_create() failed");
		}
	}
	// don't exit just loop forever until some signal is received
	while(1) {
		sleep(10);
	}
}

void clear_count(void *myn) {
	struct my_net_header *p;
	p = myn;
	if (p->mask >= htonl(0xff000000)) {
		if (p->mask >= htonl(0xffff0000)) {
			if (p->mask >= htonl(0xffffff00)) {
				if (p->mask == htonl(0xffffffff)) {
					// /32 mask
					struct my_network32 *tmp;
					tmp=myn;
					while (tmp) {
						tmp -> in.t_count= tmp -> in.t_bytes=0;
						tmp -> in.u_count = tmp -> in.u_bytes=0;
						tmp -> in.i_count = tmp -> in.i_bytes=0;
						tmp -> in.o_count = tmp -> in.o_bytes=0;

						tmp -> out.t_count = tmp -> out.t_bytes=0;
						tmp -> out.u_count = tmp -> out.u_bytes=0;
						tmp -> out.i_count = tmp -> out.i_bytes=0;
						tmp -> out.o_count = tmp -> out.o_bytes=0;
						tmp = tmp->next;
					}
				} else {
					// /24 mask
					unsigned short int i;
					struct my_network24 *tmp;
					tmp=myn;
					while (tmp) {
						for (i=0; i<256; i++) {
							tmp -> in[i].t_count= tmp -> in[i].t_bytes=0;
							tmp -> in[i].u_count = tmp -> in[i].u_bytes=0;
							tmp -> in[i].i_count = tmp -> in[i].i_bytes=0;
							tmp -> in[i].o_count = tmp -> in[i].o_bytes=0;

							tmp -> out[i].t_count = tmp -> out[i].t_bytes=0;
							tmp -> out[i].u_count = tmp -> out[i].u_bytes=0;
							tmp -> out[i].i_count = tmp -> out[i].i_bytes=0;
							tmp -> out[i].o_count = tmp -> out[i].o_bytes=0;
						}
						tmp = tmp->next;
					}
				}
			} else {
			// /16 mask
				unsigned short int i,j;
				struct my_network16 *tmp;
				tmp=myn;
				while (tmp) {
					for (i=0; i<256; i++) {
						for (j=0; j<256; j++) {
							tmp -> in[i][j].t_count= tmp -> in[i][j].t_bytes=0;
							tmp -> in[i][j].u_count = tmp -> in[i][j].u_bytes=0;
							tmp -> in[i][j].i_count = tmp -> in[i][j].i_bytes=0;
							tmp -> in[i][j].o_count = tmp -> in[i][j].o_bytes=0;

							tmp -> out[i][j].t_count = tmp -> out[i][j].t_bytes=0;
							tmp -> out[i][j].u_count = tmp -> out[i][j].u_bytes=0;
							tmp -> out[i][j].i_count = tmp -> out[i][j].i_bytes=0;
							tmp -> out[i][j].o_count = tmp -> out[i][j].o_bytes=0;
						}
					}
					tmp = tmp->next;
				}
			}
		} else {
			// /8 mask
			unsigned short int i,j,k;
			struct my_network8 *tmp;
			tmp=myn;
			while (tmp) {
				for (i=0; i<256; i++) {
					for (j=0; j<256; j++) {
						for (k=0; k<256; k++) {
							tmp -> in[i][j][k].t_count= tmp -> in[i][j][k].t_bytes=0;
							tmp -> in[i][j][k].u_count = tmp -> in[i][j][k].u_bytes=0;
							tmp -> in[i][j][k].i_count = tmp -> in[i][j][k].i_bytes=0;
							tmp -> in[i][j][k].o_count = tmp -> in[i][j][k].o_bytes=0;

							tmp -> out[i][j][k].t_count = tmp -> out[i][j][k].t_bytes=0;
							tmp -> out[i][j][k].u_count = tmp -> out[i][j][k].u_bytes=0;
							tmp -> out[i][j][k].i_count = tmp -> out[i][j][k].i_bytes=0;
							tmp -> out[i][j][k].o_count = tmp -> out[i][j][k].o_bytes=0;
						}
					}
				}
				tmp = tmp->next;
			}
		}
	}
}

void register_packet_tcp(unsigned long int src,unsigned long int dst, int size) {
	unsigned char *sp, *dp;

	sp = (unsigned char *) &src;
	dp = (unsigned char *) &dst;

	if (cfg->mynet32) {
		struct my_network32 *p;
		p = cfg->mynet32;
		while (p) {
			//felado akitol megy
			if (p->addr == src) {
				p -> out.t_count++;
				p -> out.t_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == dst) {
				p -> in.t_count++;
				p -> in.t_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet24) {
		unsigned short int ip;
		struct my_network24 *p;
		p = cfg->mynet24;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip=(sp[3]&255);
				p -> out[ip].t_count++;
				p -> out[ip].t_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip=(dp[3]&255);
				p -> in[ip].t_count++;
				p -> in[ip].t_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet16) {
		unsigned short int ip1,ip2;
		struct my_network16 *p;
		p = cfg->mynet16;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> out[ip1][ip2].t_count++;
				p -> out[ip1][ip2].t_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> in[ip1][ip2].t_count++;
				p -> in[ip1][ip2].t_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet8) {
		unsigned short int ip1,ip2,ip3;
		struct my_network8 *p;
		p = cfg->mynet8;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> out[ip1][ip2][ip3].t_count++;
				p -> out[ip1][ip2][ip3].t_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> in[ip1][ip2][ip3].t_count++;
				p -> in[ip1][ip2][ip3].t_bytes+=size;
			}
			p = p->next;
		}
	}
}

void register_packet_udp(unsigned long int src,unsigned long int dst, int size) {
	unsigned char *sp, *dp;

	sp = (unsigned char *) &src;
	dp = (unsigned char *) &dst;

	if (cfg->mynet32) {
		struct my_network32 *p;
		p = cfg->mynet32;
		while (p) {
			//felado akitol megy
			if (p->addr == src) {
				p -> out.u_count++;
				p -> out.u_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == dst) {
				p -> in.u_count++;
				p -> in.u_bytes+=size;
			}
			p = p->next;
		}
	}

	if (cfg->mynet24) {
		unsigned short int ip;
		struct my_network24 *p;
		p = cfg->mynet24;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip=(sp[3]&255);
				p -> out[ip].u_count++;
				p -> out[ip].u_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip=(dp[3]&255);
				p -> in[ip].u_count++;
				p -> in[ip].u_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet16) {
		unsigned short int ip1,ip2;
		struct my_network16 *p;
		p = cfg->mynet16;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> out[ip1][ip2].u_count++;
				p -> out[ip1][ip2].u_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> in[ip1][ip2].u_count++;
				p -> in[ip1][ip2].u_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet8) {
		unsigned short int ip1,ip2,ip3;
		struct my_network8 *p;
		p = cfg->mynet8;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> out[ip1][ip2][ip3].u_count++;
				p -> out[ip1][ip2][ip3].u_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> in[ip1][ip2][ip3].u_count++;
				p -> in[ip1][ip2][ip3].u_bytes+=size;
			}
			p = p->next;
		}
	}
}

void register_packet_icmp(unsigned long int src,unsigned long int dst, int size) {
	unsigned char *sp, *dp;

	sp = (unsigned char *) &src;
	dp = (unsigned char *) &dst;

	if (cfg->mynet32) {
		struct my_network32 *p;
		p = cfg->mynet32;
		while (p) {
			//felado akitol megy
			if (p->addr == src) {
				p -> out.i_count++;
				p -> out.i_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == dst) {
				p -> in.o_count++;
				p -> in.o_bytes+=size;
			}
			p = p->next;
		}
	}

	if (cfg->mynet24) {
		unsigned short int ip;
		struct my_network24 *p;
		p = cfg->mynet24;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip=(sp[3]&255);
				p -> out[ip].i_count++;
				p -> out[ip].i_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip=(dp[3]&255);
				p -> in[ip].i_count++;
				p -> in[ip].i_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet16) {
		unsigned short int ip1,ip2;
		struct my_network16 *p;
		p = cfg->mynet16;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> out[ip1][ip2].i_count++;
				p -> out[ip1][ip2].i_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> in[ip1][ip2].i_count++;
				p -> in[ip1][ip2].i_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet8) {
		unsigned short int ip1,ip2,ip3;
		struct my_network8 *p;
		p = cfg->mynet8;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> out[ip1][ip2][ip3].i_count++;
				p -> out[ip1][ip2][ip3].i_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> in[ip1][ip2][ip3].i_count++;
				p -> in[ip1][ip2][ip3].i_bytes+=size;
			}
			p = p->next;
		}
	}
}

void register_packet_other(unsigned long int src,unsigned long int dst, int size) {
	unsigned char *sp, *dp;

	sp = (unsigned char *) &src;
	dp = (unsigned char *) &dst;

	if (cfg->mynet32) {
		struct my_network32 *p;
		p = cfg->mynet32;
		while (p) {
			//felado akitol megy
			if (p->addr == src) {
				p -> out.o_count++;
				p -> out.o_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == dst) {
				p -> in.o_count++;
				p -> in.o_bytes+=size;
			}
			p = p->next;
		}
	}

	if (cfg->mynet24) {
		unsigned short int ip;
		struct my_network24 *p;
		p = cfg->mynet24;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip=(sp[3]&255);
				p -> out[ip].o_count++;
				p -> out[ip].o_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip=(dp[3]&255);
				p -> in[ip].o_count++;
				p -> in[ip].o_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet16) {
		unsigned short int ip1,ip2;
		struct my_network16 *p;
		p = cfg->mynet16;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> out[ip1][ip2].o_count++;
				p -> out[ip1][ip2].o_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[2]&255);
				ip2=(sp[3]&255);
				p -> in[ip1][ip2].o_count++;
				p -> in[ip1][ip2].o_bytes+=size;
			}
			p = p->next;
		}
	}
	if (cfg->mynet8) {
		unsigned short int ip1,ip2,ip3;
		struct my_network8 *p;
		p = cfg->mynet8;
		while (p) {
			//felado akitol megy
			if (p->addr == (src & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> out[ip1][ip2][ip3].o_count++;
				p -> out[ip1][ip2][ip3].o_bytes+=size;
			}
			//cel cim ide megy
			if (p->addr == (dst & p->mask)) {
				ip1=(sp[1]&255);
				ip2=(sp[2]&255);
				ip3=(sp[3]&255);
				p -> in[ip1][ip2][ip3].o_count++;
				p -> in[ip1][ip2][ip3].o_bytes+=size;
			}
			p = p->next;
		}
	}
}

void handle_frame (unsigned char buf[], int length) {
	// ez lesz itt az ip csomagunk, a tobbi kommentezve van, mert az ip csomagbol tudni fogjuk hogy mik azok
	// ugyanez a helyzet a portokkal
	static struct ip tmp_iphdr; 
	//struct tcphdr tmp_tcphdr;  
	//struct udphdr tmp_udphdr;  
	//struct icmp tmp_icmphdr;   
	//unsigned short srcport, dstport;

	if(buf[12] * 256 + buf[13] == ETHERTYPE_IP) {
		// hogyha a csomagunk fejlecebol kiderult hogy ip csomaggal van dolgunk, akkor kimasoljuk az infot belole
		memcpy (&tmp_iphdr, &(buf[14]), sizeof (tmp_iphdr));
		switch(tmp_iphdr.ip_p) {  
			case IPPROTO_UDP:
				// aztan regisztraljuk a csomagot ha kiderul, hogy udp csomag
				register_packet_udp(tmp_iphdr.ip_src.s_addr,tmp_iphdr.ip_dst.s_addr, ntohs(tmp_iphdr.ip_len));
			break;
			case IPPROTO_TCP:
				// vagy tcp
				register_packet_tcp(tmp_iphdr.ip_src.s_addr,tmp_iphdr.ip_dst.s_addr, ntohs(tmp_iphdr.ip_len));
			break;
			case IPPROTO_ICMP:
				// vagy icmp
				register_packet_icmp(tmp_iphdr.ip_src.s_addr,tmp_iphdr.ip_dst.s_addr, ntohs(tmp_iphdr.ip_len));
			break;
			default:
				// ha egyik sem
				register_packet_other(tmp_iphdr.ip_src.s_addr,tmp_iphdr.ip_dst.s_addr, ntohs(tmp_iphdr.ip_len));
			break;
		}
	}
}

void do_packet(u_char *usr, const struct pcap_pkthdr *h, const u_char *p) {
	// zaroljuk a mutexunket
	pthread_mutex_lock(&pt_lock);
	// lekezeljuk a framet
	handle_frame((unsigned char *)p, h->len);
	pthread_mutex_unlock(&pt_lock);
	// majd feloldjuk
}

void *packet_loop(void *threadid) {
	fd_set readmask;
	int pcap_fd;
	pcap_fd = pcap_fileno(pds);
	while(1) {
		FD_ZERO(&readmask);
		FD_SET(pcap_fd, &readmask);
		if(select(pcap_fd+1, &readmask, NULL, NULL, NULL)>0) {
			if(pcap_dispatch(pds, 1, do_packet, NULL) < 0) {
				syslog(LOG_ERR, "pcap_dispatch: %s\n", pcap_geterr(pds));
				daemon_stop(0);
			}
		}
	}
}

int do_pid_file(void) {
	// return 1 ha letre lehet hozni a filet
	// return 0 ha a demon mar fut
	// ettol meg lehetnek versenyhelyzetek, de elkerulhetjuk oket kis munkaval
	// ez lesz itten a pid fileunk
	FILE *f; 
	if(access(cfg->pid_file, F_OK) == 0) {
		char buff[80];
		unsigned int pid;
		// a file mar letezik, ezert csak olvasasra nyitjuk meg
		f = fopen(cfg->pid_file, "r");
		fgets(buff, sizeof(buff), f);
		// meg zarjuk, de elotte kimentettuk a pidet belole
		fclose(f);
		// konvertaljuk int-be
		pid = atoi(buff);
		syslog(LOG_INFO, "found pid-file with pid %d\n", pid);
		if(kill(pid, 0) == -1) {
			syslog(LOG_INFO, "process %d doesn't exist anymore\n", pid);
		} else {
			syslog(LOG_INFO, "process %d is still running.\n", pid);
			return 0;
		}
	}
	// most megnyitjuk pid filet irasra
	f = fopen(cfg->pid_file, "w");
	// es beleirjuk a mi pidunket
	fprintf(f, "%d\n", (int) getpid());
	// bezarjuk
	fclose(f);
	return 1;
}

int daemon_start(void) {
	unsigned short int i;
	pid_t pid;
	// daemonizalas indul
	if( (pid = fork()) < 0) 
		// ha nem sikerul a forkolas akkor kilepes
		return(-1);
	else if (pid!=0) 
		// ha megis akkor a child procesz kilep
		exit(0);
	closelog();
	for(i=0; i<FD_SETSIZE; i++)
		// bezarunk egy halom file leirot, ilyen stdin, stdout szerusegeket
		close(i);
	// mink vagyunk a session leaderek
	setsid();
	return 0;
}

void daemon_stop(int sig) {
	// toroljuk a pid filet
	unlink(cfg->pid_file);
	syslog(LOG_INFO, "Myntcd daemon terminating (%d)\n",sig);
	// befejezzunk a csomaglopast
	exit_capture();
	if (sig != 0) { 
		// kiirjuk az adatokat meg egyszer utoljara
		write_data(cfg, start_log, time(NULL));
	}
	// logolast is abbahagyjuk
	closelog();
	// kilepunk
	exit(1);
}

void write_data(struct config *conf, time_t stime, time_t ltime) {
	FILE *df;
	char dfname [255], tf [255];
	unsigned char *ipc;
	unsigned long ipn;

	//strcpy(dfname, cfg->prefix);
	if (0 == strftime(tf, 255, "%Y_%m_%d_%H_%M_%S", localtime(&ltime))) {
		sprintf(dfname, "%s/%s.dat", conf->dir, conf->prefix);
	} else {
		sprintf(dfname, "%s/%s.%s.dat", conf->dir, conf->prefix, tf);
	}
	df = fopen(dfname, "w");
	fprintf(df,"%d %d\n", (int)stime,(int)ltime);
	start_log = time(NULL);

	if (cfg->mynet32) {
		struct my_network32 *p;
		p = cfg->mynet32;
		while (p) {
			mynet_n32->addr = p->addr;
			mynet_n32->next = p->next;
			memcpy (mynet_w32, p, sizeof(struct my_network32));
			memcpy (p, mynet_n32, sizeof(struct my_network32));
			if ((0!=mynet_w32->in.t_count + mynet_w32->in.u_count + mynet_w32->in.i_count + mynet_w32->in.o_count + mynet_w32->out.t_count + mynet_w32->out.u_count + mynet_w32->out.i_count + mynet_w32->out.o_count)) {
				//write ip
				fprintf(df,"%s", intoa(p->addr));
				//write tcp trafic
				fprintf(df," %li %li", mynet_w32->in.t_count, mynet_w32->out.t_count);
				fprintf(df," %qd %qd", mynet_w32->in.t_bytes, mynet_w32->out.t_bytes);
				//write udp trafic
				fprintf(df," %li %li", mynet_w32->in.u_count, mynet_w32->out.u_count);
				fprintf(df," %qd %qd", mynet_w32->in.u_bytes, mynet_w32->out.u_bytes);
				//write icmp trafic
				fprintf(df," %li %li", mynet_w32->in.i_count, mynet_w32->out.i_count);
				fprintf(df," %qd %qd", mynet_w32->in.i_bytes, mynet_w32->out.i_bytes);
				fprintf(df," %li %li", mynet_w32->in.o_count, mynet_w32->out.o_count);
				fprintf(df," %qd %qd", mynet_w32->in.o_bytes, mynet_w32->out.o_bytes);
				fprintf(df,"\n");
			}
			p = p->next;
		}
		clear_count(conf->mynet32);
	}

	if (cfg->mynet24) {
		struct my_network24 *p;
		unsigned short int ip;
		p = cfg->mynet24;
		// pthread_mutex_lock(&pt_lock);
		while (p) {
			mynet_n24->addr = p->addr;
			mynet_n24->next = p->next;
			memcpy (mynet_w24, p, sizeof(struct my_network24));
			memcpy (p, mynet_n24, sizeof(struct my_network24));
			ipn = p->addr;
			ipc = (unsigned char *) &ipn;
			for (ip = 0; ip<=255; ip++) {
				ipc[3] = (char) ip;
				if ((0!=mynet_w24->in[ip].t_count + mynet_w24->in[ip].u_count + mynet_w24->in[ip].i_count + mynet_w24->in[ip].o_count + mynet_w24->out[ip].t_count + mynet_w24->out[ip].u_count + mynet_w24->out[ip].i_count + mynet_w24->out[ip].o_count)) {
					//write ip
					fprintf(df,"%s", intoa(ipn));
					//write tcp trafic
					fprintf(df," %li %li", mynet_w24->in[ip].t_count, mynet_w24->out[ip].t_count);
					fprintf(df," %qd %qd", mynet_w24->in[ip].t_bytes, mynet_w24->out[ip].t_bytes);
					//write udp trafic
					fprintf(df," %li %li", mynet_w24->in[ip].u_count, mynet_w24->out[ip].u_count);
					fprintf(df," %qd %qd", mynet_w24->in[ip].u_bytes, mynet_w24->out[ip].u_bytes);
					//write icmp trafic
					fprintf(df," %li %li", mynet_w24->in[ip].i_count, mynet_w24->out[ip].i_count);
					fprintf(df," %qd %qd", mynet_w24->in[ip].i_bytes, mynet_w24->out[ip].i_bytes);
					fprintf(df," %li %li", mynet_w24->in[ip].o_count, mynet_w24->out[ip].o_count);
					fprintf(df," %qd %qd", mynet_w24->in[ip].o_bytes, mynet_w24->out[ip].o_bytes);
					fprintf(df,"\n");
				}
			}
			p = p->next;
		}
		clear_count(conf->mynet24);
	}
	if (cfg->mynet16) {
		struct my_network16 *p;
		unsigned short int ip1,ip2;
		p = cfg->mynet16;
		// pthread_mutex_lock(&pt_lock);
		while (p) {
			mynet_n16->addr = p->addr;
			mynet_n16->next = p->next;
			memcpy (mynet_w16, p, sizeof(struct my_network16));
			memcpy (p, mynet_n16, sizeof(struct my_network16));
			ipn = p->addr;
			ipc = (unsigned char *) &ipn;
			for (ip1 = 0; ip1<=255; ip1++) {
				ipc[2] = (char) ip1;
				for (ip2 = 0; ip2<=255; ip2++) {
					ipc[3] = (char) ip2;
					if ((0!=mynet_w16->in[ip1][ip2].t_count + mynet_w16->in[ip1][ip2].u_count + mynet_w16->in[ip1][ip2].i_count + mynet_w16->in[ip1][ip2].o_count + mynet_w16->out[ip1][ip2].t_count + mynet_w16->out[ip1][ip2].u_count + mynet_w16->out[ip1][ip2].i_count + mynet_w16->out[ip1][ip2].o_count)) {
						//write ip
						fprintf(df,"%s", intoa(ipn));
						//write tcp trafic
						fprintf(df," %li %li", mynet_w16->in[ip1][ip2].t_count, mynet_w16->out[ip1][ip2].t_count);
						fprintf(df," %qd %qd", mynet_w16->in[ip1][ip2].t_bytes, mynet_w16->out[ip1][ip2].t_bytes);
						//write udp trafic
						fprintf(df," %li %li", mynet_w16->in[ip1][ip2].u_count, mynet_w16->out[ip1][ip2].u_count);
						fprintf(df," %qd %qd", mynet_w16->in[ip1][ip2].u_bytes, mynet_w16->out[ip1][ip2].u_bytes);
						//write icmp trafic
						fprintf(df," %li %li", mynet_w16->in[ip1][ip2].i_count, mynet_w16->out[ip1][ip2].i_count);
						fprintf(df," %qd %qd", mynet_w16->in[ip1][ip2].i_bytes, mynet_w16->out[ip1][ip2].i_bytes);
						fprintf(df," %li %li", mynet_w16->in[ip1][ip2].o_count, mynet_w16->out[ip1][ip2].o_count);
						fprintf(df," %qd %qd", mynet_w16->in[ip1][ip2].o_bytes, mynet_w16->out[ip1][ip2].o_bytes);
						fprintf(df,"\n");
					}
				}
			}
			p = p->next;
		}
		clear_count(conf->mynet16);
	}
	if (cfg->mynet8) {
		struct my_network8 *p;
		unsigned short int ip1,ip2,ip3;
		p = cfg->mynet8;
		// pthread_mutex_lock(&pt_lock);
		while (p) {
			mynet_n8->addr = p->addr;
			mynet_n8->next = p->next;
			memcpy (mynet_w8, p, sizeof(struct my_network8));
			memcpy (p, mynet_n8, sizeof(struct my_network8));
			ipn = p->addr;
			ipc = (unsigned char *) &ipn;
			for (ip1 = 0; ip1<=255; ip1++) {
				ipc[1] = (char) ip1;
				for (ip2 = 0; ip2<=255; ip2++) {
					ipc[2] = (char) ip2;
					for (ip3 = 0; ip3<=255; ip3++) {
						ipc[3] = (char) ip3;
						if ((0!=mynet_w8->in[ip1][ip2][ip3].t_count + mynet_w8->in[ip1][ip2][ip3].u_count + mynet_w8->in[ip1][ip2][ip3].i_count + mynet_w8->in[ip1][ip2][ip3].o_count + mynet_w8->out[ip1][ip2][ip3].t_count + mynet_w8->out[ip1][ip2][ip3].u_count + mynet_w8->out[ip1][ip2][ip3].i_count + mynet_w8->out[ip1][ip2][ip3].o_count)) {
							//write ip
							fprintf(df,"%s", intoa(ipn));
							//write tcp trafic
							fprintf(df," %li %li", mynet_w8->in[ip1][ip2][ip3].t_count, mynet_w8->out[ip1][ip2][ip3].t_count);
							fprintf(df," %qd %qd", mynet_w8->in[ip1][ip2][ip3].t_bytes, mynet_w8->out[ip1][ip2][ip3].t_bytes);
							//write udp trafic
							fprintf(df," %li %li", mynet_w8->in[ip1][ip2][ip3].u_count, mynet_w8->out[ip1][ip2][ip3].u_count);
							fprintf(df," %qd %qd", mynet_w8->in[ip1][ip2][ip3].u_bytes, mynet_w8->out[ip1][ip2][ip3].u_bytes);
							//write icmp trafic
							fprintf(df," %li %li", mynet_w8->in[ip1][ip2][ip3].i_count, mynet_w8->out[ip1][ip2][ip3].i_count);
							fprintf(df," %qd %qd", mynet_w8->in[ip1][ip2][ip3].i_bytes, mynet_w8->out[ip1][ip2][ip3].i_bytes);
							fprintf(df," %li %li", mynet_w8->in[ip1][ip2][ip3].o_count, mynet_w8->out[ip1][ip2][ip3].o_count);
							fprintf(df," %qd %qd", mynet_w8->in[ip1][ip2][ip3].o_bytes, mynet_w8->out[ip1][ip2][ip3].o_bytes);
							fprintf(df,"\n");
						}
					}
				}
			}
			p = p->next;
		}
		clear_count(conf->mynet8);
	}
	// pthread_mutex_unlock(&pt_lock);
	fflush(df);
	fclose(df);
}

void alarm_handler(int sig) {
	static time_t last_check = 0; 
	now++;
	if((now - last_check) > 60) { 
		// ha tobb mint 1 perc telt el a legutobbi ellenorzes ota akkor
		time_t nnow; 
		// megujitjuk az aktualis idot
		nnow = time(NULL);
		if(nnow != now) {
			if((abs(nnow - now) > 2)) {
				// de a legutobbi "most" ota meg nem telt el 2 mp
				syslog(LOG_INFO, "got signal  %d, ignoring\n",sig);
			}
			// a most az legyen most
			now = nnow;
		}
		last_check = now;
	}
	if(!(now % cfg->save_interval) && (cfg -> save_interval != 0)) {
		// ha itt az ido, akkor adatokat irjuk ki
		write_data(cfg, start_log, now);
	}
	// 1 mp mulva probald ujra
	alarm(1);
}

#define SETSIG(sig, fun, fla)   sa.sa_handler = fun; \
                                sa.sa_flags = fla; \
                                sigaction(sig, &sa, NULL);
void signal_ignore(int sig) {
	// bejovo signalokat figyelmen kivul hagyjuk
	syslog(LOG_INFO, "got signal  %d, ignoring\n",sig);
}

void signal_setup(void) {
	unsigned short int i;
	struct sigaction sa;
	for (i=1; i < NSIG; ++i)
		signal(i, signal_ignore);
	// a program leallitasat kero szignalokat atiranyitjuk magunkhoz
	SETSIG(SIGINT, daemon_stop, 0);
	SETSIG(SIGKILL, daemon_stop, 0);
	SETSIG(SIGTERM, daemon_stop, 0);
	SETSIG(SIGSEGV, daemon_stop, 0);
	// ez meg a belso oraert felelos meg a fileokba irasert
	SETSIG(SIGALRM, alarm_handler, 0);
	SETSIG(SIGUSR1, reload_config, 0);

	// ez meg itt arra figyelmeztet ha a gyermek processunk kilepett
//	SETSIG(SIGCHLD, child_finished, 0);
}

void usage(void) {
	fprintf(stderr, "Usage: %s [-d] [-c filename]\n\n", progname);
	fprintf(stderr, "\t-c\tSpecify alternative configuration file\n");
	fprintf(stderr, "\t-d\tNot start like a daemon\n\n");
}

void process_options(int argc, char **argv) {
	int c;
	fname = strdup(CONFIG);
	
	while ((c = getopt( argc, argv, "c:d" )) != EOF) { 
		// ertelmezzuk a command line opciokat
		switch (c) {
		case 'c':
			// modositsuk a configfilet helyet
			free(fname);
			fname = strdup(optarg); 
		break;
		case 'd':
			// meg demonkent akarjuk futtatni a programot
			daem = 0; 
		break;
		case '?': 
		default:
			// help
			usage();
			exit(1);
		}
	}
   
	argc -= optind;
	argv += optind;

	if (argc > 1) {
		// megmondjuk hogy hogy kell hasznalni a programot
		usage(); 
		exit(1);
	}
}

int main(int argc, char **argv) {
	// eltesszuk a programunk binarisanak nevet kesobbi felhasznalasra
	progname = argv[0]; 
	// system logger felkeszitese
	openlog("myntcd", 0, LOG_DAEMON); 
	// effective user id megkaparintasa
	if(geteuid() != 0) {
		syslog(LOG_ERR, "must be superuser to run nacctd\n");
		// ha nem rootkent van inditva akkor kilepunk
		exit(1); 
	}
	// parancssori parameterek feldolgozasa
	process_options(argc, argv); 
	// konfiguracios file beolvasasa es a cfg strukturaba bepakolasa
	cfg = read_config(fname); 

	if (cfg != NULL) { 
		// ha a konfiguralas sikeres volt
		if (daem) { 
			// ha daemonkent fut a cuccos
			if(daemon_start() != -1) { 
				// ha a daemonunk szepen leforkolodott akkor...
				openlog("myntcd", 0, LOG_DAEMON);
				syslog(LOG_INFO, "Myntcd daemon forked\n");
			} else {
				syslog(LOG_ERR, "couldn't fork: %m\n");
				syslog(LOG_INFO, "Myntcd daemon aborting\n");
				exit(1);
			}
		} else { 
			// ha pedig konzol modban
			syslog(LOG_INFO, "Start myntcd console mode. %s\n", fname);
		}
		if(!do_pid_file()) { 
			syslog(LOG_ERR, "daemon already running or stale pid-file\n");
			exit(1);
		}
		init_cfg(cfg);
		// ellopkodjuk a signal handlereket
		signal_setup();
		// aztan meg a csomagokat is ellopjuk
		init_capture();
		// es elkezdjuk az accountingot is
		do_acct();
		// aztan kilepunk belole
 		exit_capture();
		return 0;
	} else {
		// nem talaltunk config filet de meghato
		syslog(LOG_INFO, "Not found config file: %s\n", fname);
		err_quit();
	}
	return 1;
}
