#pragma once

#include <stdbool.h>

struct Parametry {
	unsigned long long id; // id страницы
	bool type; // тип страницы, true - сообщество, false - страница
	char *domain; // домен страницы, меньше приоритет
	char *zagolovok; // имя группы или страницы, нужен будет RSS ленте
	char *opisanie; // описание
	char *lenta; // лента страницы, необработанная
	char *info; // информация о странице, необработанная
	unsigned short kolichestvo;
	unsigned short filter;
};

char *poluchit_url_zaprosa_lenty(struct Parametry);
char *poluchit_url_zaprosa_info_stranicy(struct Parametry);
