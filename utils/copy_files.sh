#!/bin/sh

for i in $(echo $HOME/code/pastaholic/*) ; do
	cp -r $i $HTML/pastaholic/
done

mkhomepg -p
