#!/bin/bash

chkdata() {
	F=$1
	CRT=$2
	KEY=$3
	if [[ "$CRT" != "" && "$KEY" != "" ]] ; then
		if [[ ! -f "$CRT" ]] ; then
			echo "[WARN] CERTIFICATE FILE ${CRT} MISSING FOR ${F}" ;
		else 
			echo -n "Checking ${CRT}" ;
			CHK=$(openssl x509 -in "${CRT}" -text -noout >/dev/null 2>&1 ; echo $?);
			if [[ $CHK -ne 0 ]] ; then
				echo " FAILED!" ;
			else
				echo " OK" ;
			fi
		fi
		if [[ ! -f "$KEY" ]] ; then
			echo "[WARN] KEY FILE ${KEY} MISSING FOR ${F}" ;
		else
			echo -n "Checking ${KEY}" ;
			CHK=$(openssl rsa -in "${KEY}" -check -noout >/dev/null 2>&1 ; echo $?);
			if [[ $CHK -ne 0 ]] ; then
				echo " FAILED!" ;
			else
				echo " OK" ;
			fi
		fi
	
		if [[ -f "$CRT" && -f "$KEY" ]] ; then
			echo -n "Checking that key and certificate match";
			MDCRT=$(openssl x509 -noout -modulus -in "${CRT}" | openssl md5) ;
			MDKEY=$(openssl rsa -noout -modulus -in "${KEY}" | openssl md5) ;
			if [[ "$MDCRT" != "$MDKEY" ]] ; then
				echo " FAILED!" ;
			else
				echo " OK" ;
			fi
		fi
		echo "---" ;
	elif [[ "$CRT" != ""  || "$KEY" != "" ]] ; then
		echo "[WARN] Check SSL config of ${F}";
		echo "---" ;
	fi
}

if [[ -d /etc/apache2/sites-enabled ]] ; then
	echo "Checking enabled apache vhosts" ;
	for FIL in /etc/apache2/sites-enabled/* ; do
		CRT=$(grep 'SSLCertificateFile' "${FIL}" | grep -E -v '^[[:space:]]*#' | awk '{print $2}' | head -n 1) ;
		KEY=$(grep 'SSLCertificateKeyFile' "${FIL}" | grep -E -v '^[[:space:]]*#' | awk '{print $2}' | head -n 1) ;
		chkdata "$FIL" "$CRT" "$KEY" ;
	done
fi

if [[ -d /etc/nginx/sites-enabled ]] ; then
	echo "Checking enabled nginx vhosts" ;
	for FIL in /etc/nginx/sites-enabled/* ; do
		CRT=$(grep 'ssl_certificate' "${FIL}" | grep -E -v '^[[:space:]]*#' | awk '{print $2}' | head -n 1) ;
		CRT=${CRT%;}
		KEY=$(grep 'ssl_certificate_key' "${FIL}" | grep -E -v '^[[:space:]]*#' | awk '{print $2}' | head -n 1) ;
		KEY=${KEY%;}
		chkdata "$FIL" "$CRT" "$KEY" ;
	done
fi