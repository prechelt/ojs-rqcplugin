"""
Commands for docker and docker-compose.
"""
import os
import re
import typing as tg

import fabric

import z.fabric.base as b

COMPOSE_FN = "z/docker-compose.yml"
COMPOSE_PROJECTNAME = os.environ['OJS_COMPOSE_PROJECTNAME']
COMPOSE_DBSERVICE = os.environ['OJS_COMPOSE_DBSERVICE']

@fabric.task
def db_backup(c):
    """
    Execute  make_backup.sh backup  in existing db container.
    For restore, see z.fabric.workflows; it is more complex.
    """
    exec(c, service=COMPOSE_DBSERVICE, cmd="/make_backup.sh backup")


#@fabric.task
def db_restore_raw(c, mode=""):
    """Not a task; only workflows.db_restore ensures the proper precondition."""
    assert mode == 'restore-no-matter-what'
    exec(c, service=COMPOSE_DBSERVICE, cmd="/make_backup.sh restore")


@fabric.task
def db_run(c):
    """Start the appropriate database service."""
    dc(c, cmd=f"up {COMPOSE_DBSERVICE} -d")


@fabric.task
def db_stop(c):
    """Stop db service (but keep the container)."""
    dc(c, cmd=f"stop {COMPOSE_DBSERVICE}")


@fabric.task
def dc(c, cmd=""):
    """docker compose command with fixed compose file."""
    # the docker compose vocab:
    # up = create + start; down = stop + remove;
    # push; pull;  pause; unpause;  kill;  restart;
    # run (in new container); exec (in running container)
    c.run(f"docker compose -f {COMPOSE_FN} -p {COMPOSE_PROJECTNAME} {cmd}")


@fabric.task
def docker(c, cmd=""):
    """plain docker command"""
    c.run(f"docker {cmd}")


@fabric.task
def exec(c, service="", cmd=""):
    """Execute a command in the existing container of the given service."""
    b.stopifnot(service and cmd, "must provide --service name --cmd command")
    cmd = f"docker exec -t {_containername(service)} {cmd}"
    b.run_lor(c, cmd)


@fabric.task
def shell(c, service=""):
    """Start an interactive shell in the existing container of the given service."""
    b.stopifnot(service, "must provide --service name")
    cmd = f"docker exec -ti {_containername(service)} /bin/bash"
    c.run(cmd, pty=True)


def _containername(service):
    return f"{service}_1"



