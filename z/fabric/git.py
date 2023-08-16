"""
Commands related to Git.
"""
import fabric

import z.fabric.base as b

@fabric.task
def rebase(c, basebranch, mybranch, level=1):
    """checkout basebranch, pull, push, checkout mybranch, rebase"""
    def do(cmd):
        b.titled_run(c, cmd, level=level+1, titled=True)
    b.log(f"REBASE on {basebranch}:", level=level)
    do(f"git checkout {basebranch}")
    do(f"git pull --ff-only upstream {basebranch}")
    do("git push")
    do(f"git checkout {mybranch}")
    do(f"git rebase {basebranch}")


@fabric.task
def submodule_update(c, level=1):
    """git submodule update --init --recursive"""
    def do(cmd):
        b.titled_run(c, cmd, level=level, titled=True)
    do("git submodule update --init --recursive")
