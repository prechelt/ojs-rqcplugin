"""
Commands related to PHP and Composer.
"""
import fabric

import z.fabric.base as b

@fabric.task
def composer_update(c, level=1):
    """OJS perform composer module updates"""
    def do(cmd):
        b.titled_run(c, cmd, level=level, titled=True)
    do("composer --working-dir=lib/pkp update")

