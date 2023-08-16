"""
Commands related to node.js npm.
"""
import fabric

import z.fabric.base as b

@fabric.task
def npm_updates(c, level=1):
    """OJS npm install and build"""
    def do(cmd):
        b.titled_run(c, cmd, level=level, titled=True)
    do("npm install")
    do("npm run build")
