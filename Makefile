TAREXCLUDES=\
  --exclude 'rqc/.git*'\
  --exclude rqc/patches\
  --exclude rqc/tar\
  --exclude rqc/tests\
  --exclude rqc/z

all:
	### There is no 'all' target.  Possible targets:
	egrep '^[a-zA-Z][a-zA-Z0-9_]*:' Makefile


tar.gz:
	cd ..; tar cvzf rqc/tar/rqc.tar.gz $(TAREXCLUDES) rqc
	echo "### wrote rqc/tar/rqc.tar.gz; now rename it, upload it, make PR with XML snippet"
	grep '<release>' version.xml
