#!/usr/bin/env bash
# https://www.postgresql.org/docs/9.5/static/backup.html
# create a full DB backup into a hardcoded directory, or restore it.
# Keep multiple such backups around.

BACKUPDIR=$HOME/backup
BACKUPFILENAME=ojs_postgres_backup.sql
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
    sudo -u postgres pg_dumpall -U postgres --clean >$BACKUPFILE
else
    #--- restore the most recent backup:
    sudo -u postgres psql -U postgres -f $BACKUPFILE postgres
fi
END=`date -Iseconds`
SIZE=`du $BACKUPFILE`
echo "cmd/start/end/KB $1 $START $END $SIZE" >> $LOGFILE
