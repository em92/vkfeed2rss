#pragma once

#include <stdbool.h>

struct Parametry {
	unsigned long long id;
	bool tip; // тип страницы: true - паблик, false - пользовательская страница
	unsigned short kolichestvo;
	unsigned short filter;
};

char *poluchit_url_zaprosa(struct Parametry);
