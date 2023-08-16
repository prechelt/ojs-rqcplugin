"""Low-level helpers to be used everywhere."""

import os
import os.path
import re
import sys

import colorama
import fabric

SCRATCH_DIR = "z/fabscratch"

def log(*args, level=1, **kwargs):
    color = colorama.Back.BLUE
    uncolor = colorama.Style.RESET_ALL
    hashesN = max(12 - 2*level, 1)
    args = (color, hashesN*"#") + args + (hashesN*"#", uncolor)
    print(*args, file = sys.stderr, **kwargs)


def make_file(pathname: str, contents: str) -> str:
    def is_same(fn, content):
        with open(fn, mode='rb') as f:
            oldcontent = f.read().decode('utf8')
        return oldcontent == content
    if pathname.startswith('~'):
        pathname = pathname.replace('~', os.environ['HOME'], 1)
    if os.path.exists(pathname) and is_same(pathname, contents):
        return  # no need to write it again
    with open(pathname, mode='wb') as f:
        f.write(contents.encode('utf8'))
    log(f"-- wrote '{pathname}'")


def press_y_to_continue():
    response = ""
    while response != "y":
        response = input("Press 'y' to continue  ")


def stopif(cond, msg):
    if cond:
        print("####", msg, file=sys.stderr)
        sys.exit(1)


def stopifnot(cond, msg):
    stopif(not cond, msg)


def titled_run(c, cmd, level=1, titled=False):
    if titled:
        log(cmd, level=level)
    c.run(cmd)
