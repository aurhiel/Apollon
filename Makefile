build:
	docker-compose stop;
	docker-compose build;
	docker-compose up -d;

boot:
	docker-compose up -d;
	docker-compose exec php-fpm bash;

reboot:
	docker-compose stop;
	docker-compose up -d;

shell:
	docker-compose exec php-fpm bash;

shell-db:
	docker-compose exec mysql bash -c "mysql -u root -pdbrootpw sf_apollon";

shutdown:
	docker-compose stop;
