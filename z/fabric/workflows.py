"""
Compound commands.
"""
import fabric

from z.fabric import git, npm, php
import z.fabric.base as b

BASEBRANCH = 'main'
MY_BRANCH = 'rqc34'

@fabric.task
def rebase_on_upstream(c, basebranch=BASEBRANCH, branch=MY_BRANCH, level=1):
    """rebase MY_BRANCH on upstream/BASEBRANCH"""
    b.log("REBASE ON UPSTREAM", level=level)
    git.rebase(c, basebranch=basebranch, branch=branch, level=level+1)
    git.submodule_update(c, level=level+1)
    php.composer_update(c, level=level+1)
    npm.npm_updates(c, level=level+1)
    b.titled_run(c, "git push --force", level=level+1, titled=True)
    b.log("rebase-on-upstream successful. DONE.", level=level+1)
