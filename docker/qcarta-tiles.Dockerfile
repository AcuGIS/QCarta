# https://docs.docker.com/guides/golang/build-images/
FROM golang:1.25.6 AS build-stage

WORKDIR /app
COPY ../installer/qcarta-tiles/go.mod ../installer/qcarta-tiles/main.go /app/

RUN mkdir -p /qcarta-cache && \
    chmod 0777 /qcarta-cache && \
    sed -i.save 's|127.0.0.1|qcarta-tiles|' /app/main.go && \
    sed -i.save 's|/var/www/data/qcarta-tiles/cache_data|/qcarta-cache|' /app/main.go

RUN go mod download && \
    CGO_ENABLED=0 GOOS=linux go build -o qcarta-tiles

# Deploy the application binary into a lean image
FROM gcr.io/distroless/base-debian11 AS build-release-stage

ENV QGIS_SERVER_URL=http://web/cgi-bin/qgis_mapserv.fcgi
WORKDIR /

COPY --from=build-stage /app/qcarta-tiles /qcarta-tiles
COPY --from=build-stage --chown=nonroot:nonroot /qcarta-cache /qcarta-cache

VOLUME /qcarta-cache

EXPOSE 8011

USER nonroot:nonroot
ENTRYPOINT ["/qcarta-tiles"]
