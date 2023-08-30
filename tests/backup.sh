#!/usr/bin/env bash
# https://www.postgresql.org/docs/current/static/backup.html
# create a full DB backup into a hardcoded directory, or restore it.
# Keep multiple such backups around.
# Assumes localhost and default port.

BACKUPDIR=$HOME/backup
BACKUPFILENAME=ojs_db_backup.sql
BACKUPFILE=$BACKUPDIR/$BACKUPFILENAME
LOGFILE=$BACKUPDIR/backup.log

#----- check and store arguments:
if [[ $# -ne 1 || ( $1 != backup && $1 != restore ) ]]; then
  echo "usage:  backup.sh  backup|restore"
  echo "    backs up to/restores from $BACKUPFILE"
  exit 1
fi

# set -x
START=`date -Iseconds`
if [[ $1 == backup ]]; then
    #--- back up the last backup:
    mv -f --backup=numbered $BACKUPFILE $BACKUPFILE.bak
    #--- make new backup:
    # sudo -u postgres pg_dumpall -U postgres --clean >$BACKUPFILE
    pg_dump -U ojs --dbname=ojs --clean --file=$BACKUPFILE
fi
if [[ $1 == restore ]]; then
    #--- restore the most recent backup:
    postgres psql --file=$BACKUPFILE ojs ojs
fi
END=`date -Iseconds`
SIZE=`du $BACKUPFILE`
echo "cmd/start/end/KB $1 $START $END $SIZE" >> $LOGFILE
