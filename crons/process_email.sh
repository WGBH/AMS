#!/bin/bash

#####################################
#                                   #
#  Shell Script for sending emails  #
#  to users that are in queue       #
#  This script will prevent         #
#  from running method more         #
#  than one time                    #
#  Developer : Nouman Tayyab        #
#  nouman@geekschicago.com          #
#                                   #
#####################################

source /root/.bash_profile

HOST=`/bin/hostname`
PID_FILE="PIDs/process_email.pid"
OUTPUT_FILE="cronlog/process_email.log"
BASEDIR="/var/www/html/"
DATE=`date`

PHP_PATH=`which php`
PID_FULLPATH=$BASEDIR$PID_FILE
OUTPUT_FILE=$BASEDIR$OUTPUT_FILE

touch $PID_FULLPATH
touch $OUTPUT_FILE

PID=`cat $PID_FULLPATH`
PIDD=/proc/$PID 
if [ -n "$PID" ]  && [ -d $PIDD ]
then
echo "Already running ...."
else
CMD="$PHP_PATH ${BASEDIR}index.php crons processemailqueues > $OUTPUT_FILE 2>&1 & echo $! > $PID_FULLPATH"
MSG="ENV [$ENVIRONMENT]"
echo $MSG >> $OUTPUT_FILE
MSG="Starting cron at $DATE"
echo $MSG >> $OUTPUT_FILE
echo $CMD >> $OUTPUT_FILE

$PHP_PATH ${BASEDIR}index.php crons processemailqueues > $OUTPUT_FILE 2>&1 & echo $! > $PID_FULLPATH
PID=`cat $PID_FULLPATH`
echo "Started Cron [$PID]"
echo "To view logs Use tail -f $OUTPUT_FILE"
fi