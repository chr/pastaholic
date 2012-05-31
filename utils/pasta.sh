#!/bin/sh

url=http://chr.tx0.org/p/pasta.php

usage() {
	p=$(basename $0)
	echo "usage: $p [--title \"a title\" ] [--lang \"language\"] --pasta FILE"
	echo
	echo "short option names: -t (--title), -l (--lang), -p (--pasta)"
	echo "note: if '--lang (-l) is omitted, 'text' is assumed"
	exit $1
}

if [ "$#" -eq 0 ]
then
	usage 1
fi

while [ "$#" -gt 0 ]
do
	case $1 in
		-t|-title|--title)
			o=$2
			if [ "${o%-*}-" != '-' ]
			then
				title=$o
				unset o
				shift
			fi
			shift
			;;
		-l|-lang|--lang)
			o=$2
			if [ "${o%-*}-" != '-' ]
			then
				lang=$o
				unset o
				shift
			fi
			shift
			;;
		-p|-pasta|--pasta)
			o=$2
			if [ "${o%-*}-" != '-' ]
			then
				pasta=$2
				if [ ! -f "$pasta" ]
				then
					usage 2
				fi
				unset o
				shift
			else
				usage 3
			fi
			shift
			;;
		-h|-help|--help)
			usage 0
			;;
		*)
			break
			;;
	esac
done

if [ -z "$pasta" ]
then
	usage 4
fi

curl -v -s -F "title=$title" -F "lang=$lang" -F "pasta=<$pasta" $url 2>&1 | \
  awk '/Location/ { print $3 }'
