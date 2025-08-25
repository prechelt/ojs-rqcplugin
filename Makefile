TAREXCLUDES=\
  --exclude 'rqc/.git*'\
  --exclude 'rqc/.idea'\
  --exclude rqc/patches\
  --exclude rqc/tar\
  --exclude rqc/tests\
  --exclude rqc/z

all:
	### There is no 'all' target.  Possible targets:
	egrep '^[a-zA-Z][a-zA-Z0-9_]*:' Makefile


tar.gz:
	mkdir -p tar; # make the tar dir if not present
	rm -f tar/rqc.tar.gz # remove the old tar if present
	cd ..; tar cvzf rqc/tar/rqc.tar.gz $(TAREXCLUDES) rqc
	echo "### wrote rqc/tar/rqc.tar.gz; now rename it, upload it, make PR with XML snippet"
	grep '<release>' version.xml
