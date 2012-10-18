#!/bin/bash
#set -x

# Configure this!
MESSENTE_API_USERNAME=00000000000000000000000000000000
MESSENTE_API_PASSWORD=11111111111111111111111111111111




# ---------------------------------------
# Do not modify anything below this line
# ---------------------------------------

if [ ! "$#" -eq 2 ]; then
 echo "ERROR 2 arguments required, $# provided"
 echo "-------------------------------------------------"
 echo "Usage: bash $0 full_phone_number text_message"
 echo "Example: bash $0 4463773683 \"This is a test message\""
 echo "-------------------------------------------------"
 exit 1
fi
echo -n "Sending SMS..."
curl -o /tmp/send_sms_result \
 -s http://api2.messente.com/send_sms/ \
 -d username=${MESSENTE_API_USERNAME} \
 -d password=${MESSENTE_API_PASSWORD} \
 --data-urlencode "to=$1" \
 --data-urlencode "text=$2"
API_RESULT=$( cat /tmp/send_sms_result | sed 's/\( \)\(.*\)/\1/' )
API_RESULT_FULL=$( cat /tmp/send_sms_result )
if [ $API_RESULT == "OK" ]; then
 echo " done" 
 exit 0
else
 echo " failed (${API_RESULT_FULL})"
 exit 1
fi
