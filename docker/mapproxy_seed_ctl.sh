#!/bin/bash -e

OP=${1}
LAYER_ID=${2}
SEED_PID=-1

cd /var/www/data/mapproxy

case ${OP} in
	start)
		/usr/bin/mapproxy-seed -c 2 --cleanup=ALL --seed=ALL \
			-f mapproxy.yaml -s /var/www/data/layers/${LAYER_ID}/seed.yaml \
			--progress-file /var/www/data/layers/${LAYER_ID}/mapproxy_seed_progress --continue \
			1>/var/www/data/layers/${LAYER_ID}/seed.log 2>&1 &

		echo $! > /var/www/data/layers/${LAYER_ID}/seed.pid
		;;
	stop)
		if [ -f /var/www/data/layers/${LAYER_ID}/seed.pid ]; then
			SEED_PPID=$(cat /var/www/data/layers/${LAYER_ID}/seed.pid)
			while [ -d /proc/${SEED_PPID} ]; do
				pkill -P ${SEED_PPID}
				sleep 2;
			done
		fi
		;;
	status)
		if [ -f /var/www/data/layers/${LAYER_ID}/seed.pid ]; then
			SEED_PID=$(cat /var/www/data/layers/${LAYER_ID}/seed.pid)
		fi
		
		if [ -d /proc/${SEED_PID} ]; then
			echo "  Active: active (running)"
			echo "  Main PID: ${SEED_PID} (mapproxy-seed)"
			SEED_TIME=$(ps -p ${SEED_PID} -o pid,comm,etime | tail -n 1 | sed 's/[ \t]\+/ /g' | cut -f4 -d' ')
			echo "  CPU: ${SEED_TIME} s"
		else
			#rm -f /var/www/data/layers/${LAYER_ID}/seed.pid
			echo "  Active: inactive (dead)"
		fi
		;;
	*)
		echo "Error: Invalid op ${1}"; exit 1;
		;;
esac