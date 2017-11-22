#!/bin/bash

DBUSER=root
DBPASSWORD=notreally
DBSNAME=folkloredb
DBNAME=dflfulldb
DBSERVER=localhost

fCreateTable=""
fInsertData=""
echo "Copying database ... (may take a while ...)"
DBCONN="-h ${DBSERVER} -u ${DBUSER} --password=${DBPASSWORD}"
echo "DROP DATABASE IF EXISTS ${DBNAME}" | mysql ${DBCONN}
echo "CREATE DATABASE ${DBNAME}" | mysql ${DBCONN}
for TABLE in `echo "SHOW TABLES" | mysql $DBCONN $DBSNAME | tail -n +2`; do
        createTable=`echo "SHOW CREATE TABLE ${TABLE}"|mysql -B -r $DBCONN $DBSNAME|tail -n +2|cut -f 2-`
        fCreateTable="${fCreateTable} ; ${createTable}"
        insertData="INSERT INTO ${DBNAME}.${TABLE} SELECT * FROM ${DBSNAME}.${TABLE}"
        fInsertData="${fInsertData} ; ${insertData}"
done;
echo "$fCreateTable ; $fInsertData" | mysql $DBCONN $DBNAME
