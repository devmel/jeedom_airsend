##
# AirSend Install dependancies
##

PROGRESS_FILE=/tmp/dependancy_airsend_in_progress
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "AirSend - Debut de l'installation des dependances ..."
pwd
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"

sudo $1 install -y wget tar gpg 
echo 30 > ${PROGRESS_FILE}

sudo rm -rf bin
echo 40 > ${PROGRESS_FILE}

wget $2 -O service.tgz
echo 70 > ${PROGRESS_FILE}

tar -xvf service.tgz
echo 90 > ${PROGRESS_FILE}

rm service.tgz
echo 100 > ${PROGRESS_FILE}

sudo chmod -R 777 bin

echo "-"
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
echo "Fin de l'installation des dependances ..."
echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++"
sudo rm ${PROGRESS_FILE}
