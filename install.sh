#!/bin/sh

cd daemon/
./autostuff
./configure --sysconfdir=/usr/local/etc/myntcd
make

mkdir -p /usr/local/myntcd/bin
mkdir -p /usr/local/myntcd/data
mkdir -p /usr/local/myntcd/rrd
mkdir -p /usr/local/etc/myntcd

cd ..
cp bin/* /usr/local/myntcd/bin/
cp daemon/src/myntcd /usr/local/myntcd/bin/
cp -r docs /usr/local/myntcd/
ln -s /usr/local/etc/myntcd /etc/myntcd
cp etc/myntcd/* /usr/local/etc/myntcd/

# innen debian specifikus
cp etc/init.d/myntcd /etc/init.d/
cd /etc/init.d/
update-rc.d myntcd defaults
cd -

