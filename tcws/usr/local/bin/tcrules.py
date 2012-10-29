#!/usr/bin/python

#*************************************************************************************
#* SETTINGS                                                                          *
#*************************************************************************************
#* DB 										     *
import datetime
import time
import MySQLdb
import subprocess
import re

user = "username"
pwd = "password"
dbname = "traffic"
server = "ipaddress"

#* FILES                                                                             *
default_tcrules = "/etc/shorewall/tcrules.def"
tcrules = "/etc/shorewall/tcrules"
default_tcrules_6 = "/etc/shorewall6/tcrules.def"
tcrules_6 = "/etc/shorewall6/tcrules"

#* TCRULES                                                                           *
#download_mark = "5"
#upload_mark = "6"
download_class_prefix = "2:"
upload_class_prefix = "1:"
blackliststart = 3001
blacklistmaxnum = 100
blackliststart_6 = 4001
blacklistmaxnum_6 = 100

#*************************************************************************************

tnow = datetime.datetime.now()
tmin5minit = datetime.timedelta(minutes=-5)
t_prev = tnow + tmin5minit
t_prev = t_prev.strftime("%Y-%m-%d %H:%M:00")

def ipcheck(ip):

    ipv4regexp = "^((25[0-5]|2[0-4]\d|[01]?\d\d|\d)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d|\d)$"

    ipv6regexp = "^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|"
    ipv6regexp += "(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|"
    ipv6regexp += "((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|"
    ipv6regexp += "(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|"
    ipv6regexp += ":((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|"
    ipv6regexp += "(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|"
    ipv6regexp += "((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|"
    ipv6regexp += "(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|"
    ipv6regexp += "((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|"
    ipv6regexp += "(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|"
    ipv6regexp += "((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|"
    ipv6regexp += "(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|"
    ipv6regexp += "((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|"
    ipv6regexp += "(:(((:[0-9A-Fa-f]{1,4}){1,7})|"
    ipv6regexp += "((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$"

    if re.match(ipv4regexp, ip):
	return "ipv4"
    elif re.match(ipv6regexp, ip):
	return "ipv6"
    else:
	return 0

db = MySQLdb.connect(server, user, pwd, dbname)
cursor = db.cursor()

sql = "SHOW TABLE STATUS LIKE '%filter'"

cursor.execute(sql)
row = cursor.fetchone()
update_time = row[12].strftime("%Y-%m-%d %H:%M:%S")

if t_prev <= update_time:

    fp = open(default_tcrules, "r")
    default_rows = fp.readlines()
    fp.close()
    
    fp = open(default_tcrules_6, "r")
    default_rows_6 = fp.readlines()
    fp.close()

    # Ha hibara fut hogy nincs lastline akkor egy ures sor kell a file vegere!
    for i in range(len(default_rows)):
	if default_rows[i] == "#LAST LINE -- ADD YOUR ENTRIES BEFORE THIS ONE -- DO NOT REMOVE\n":
	    lastline = i

    for i in range(len(default_rows_6)):
	if default_rows_6[i] == "#LAST LINE -- ADD YOUR ENTRIES BEFORE THIS ONE -- DO NOT REMOVE\n":
	    lastline_6 = i

    sql = "SELECT ip FROM filter WHERE blacklist = '%d'" % (1)

    counter = blackliststart
    counter_6 = blackliststart_6
    cursor.execute(sql)
    results = cursor.fetchall()
    for row in results:
	if ipcheck(row[0]) == "ipv4":
	    #default_rows.insert(lastline, download_mark+"\t0.0.0.0/0\t"+row[0]+"\tall\n")
	    default_rows.insert(lastline, download_class_prefix+str(counter)+"\t0.0.0.0/0\t"+row[0]+"\tall\n")
	    lastline += 1
	    #default_rows.insert(lastline, upload_mark+"\t"+row[0]+"\t0.0.0.0/0\tall\n")
	    default_rows.insert(lastline, upload_class_prefix+str(counter)+"\t"+row[0]+"\t0.0.0.0/0\tall\n")
	    lastline += 1
	
	    if counter >= (blackliststart - 1) + blacklistmaxnum:
		counter = blackliststart
	    else:
		counter += 1
	elif ipcheck(row[0]) == "ipv6":
	    default_rows_6.insert(lastline_6, download_class_prefix+str(counter_6)+"\t::/0\t\t\t\t"+row[0]+"\tall\n")
	    lastline_6 += 1
	    default_rows_6.insert(lastline_6, upload_class_prefix+str(counter_6)+"\t"+row[0]+"\t::/0\t\t\t\tall\n")
	    lastline_6 += 1
	
	    if counter_6 >= (blackliststart_6 - 1) + blacklistmaxnum_6:
		counter_6 = blackliststart_6
	    else:
		counter_6 += 1
    
    fp = open(tcrules, "w")
    for i in range(len(default_rows)):
	fp.write(default_rows[i])
    fp.close()
    
    fp = open(tcrules_6, "w")
    for i in range(len(default_rows_6)):
	fp.write(default_rows_6[i])
    fp.close()

    subprocess.call("/etc/init.d/shorewall refresh", shell=True)
    subprocess.call("/etc/init.d/shorewall6 refresh", shell=True)

db.close()
