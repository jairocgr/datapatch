FROM mysql:5.6.40

RUN echo ""                             >  /etc/mysql/conf.d/connection.cnf
RUN echo "[mysqld]"                     >> /etc/mysql/conf.d/connection.cnf
RUN echo "max_allowed_packet  = 2048MB" >> /etc/mysql/conf.d/connection.cnf
RUN echo "wait_timeout        = 604800" >> /etc/mysql/conf.d/connection.cnf
RUN echo ""                             >> /etc/mysql/conf.d/connection.cnf
