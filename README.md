# Automated Docker Service Pruning

Crontabs are fickle, lets just make it a service we can run on all nodes!

Environment variables:
 * INTERVAL_SECONDS: Delay before running again
 * DOCKER_HOST: Allows overriding default /var/run/docker.sock

Simply deploy this service to affected nodes, with access to the docker socket (or via DOCKER_HOST envvar)

Usage:
```bash
docker service create \
    --name pruner \
    --mount type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock \
    --mode=global \
    --env INTERVAL_SECONDS=86400 \
    --restart-max-attempts=0 \
    --restart-condition=any \
        matthewbaggett/pruner
```