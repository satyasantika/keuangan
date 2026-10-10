# Container cadangan harian MySQL (cron 01:30 WIB). Lihat docs/runbook-cadangan.md.
FROM alpine:3.20
RUN apk add --no-cache mysql-client mariadb-connector-c gzip coreutils tzdata findutils \
 && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime && echo "Asia/Jakarta" > /etc/timezone
COPY --chmod=755 docker/backup/backup.sh /usr/local/bin/backup.sh
COPY --chmod=755 docker/backup/restore.sh /usr/local/bin/restore.sh
RUN echo '30 1 * * * /usr/local/bin/backup.sh >> /proc/1/fd/1 2>> /proc/1/fd/2' > /etc/crontabs/root
CMD ["crond", "-f", "-l", "8"]
