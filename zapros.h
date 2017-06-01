#pragma once

#include <stdbool.h>

struct Parametry {
	unsigned long long id; // id страницы
	bool type; // тип страницы, true - сообщество, false - страница
	char *domain; // домен страницы, приоритет
	unsigned short kolichestvo;
	unsigned short filter;
};

char *poluchit_url_zaprosa(struct Parametry);
