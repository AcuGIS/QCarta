#!/bin/bash -e

for i in 2 3 4 6 5 7 8 9; do
	mkdir /var/www/cache/layers/${i}
	for l in 0 1 2 3 4 5 6 7 8 9 a b c d e f; do
		mkdir /var/www/cache/layers/${i}/${l}
	done
done