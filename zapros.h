#pragma once

#include <stdbool.h>

struct Parametry { // форма запроса для VK
	unsigned long long id; // id страницы
	bool type; // тип страницы, true - сообщество, false - страница
	char *domain; // домен страницы
	char *zagolovok; // имя страницы
	char *opisanie; // описание
	char *lenta; // лента страницы, необработанная
	char *info; // информация о странице, необработанная
	unsigned short kolichestvo; // количество записей в RSS ленте, не больше 100
	unsigned short filter; // фильтр, не работает
	bool verbose;
};

char *poluchit_url_zaprosa_lenty(struct Parametry);
char *poluchit_url_zaprosa_info_stranicy(struct Parametry);
