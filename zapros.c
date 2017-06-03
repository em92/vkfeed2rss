#include <stdio.h>
#include <string.h>

#include "zapros.h"
#include "info.h"

char *poluchit_url_zaprosa_lenty(struct Parametry stranica)
{
	static char url[72];
		
	if (stranica.type == true && stranica.domain == NULL) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?owner_id=-%llu&count=%u", stranica.id, stranica.kolichestvo) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.type == false && stranica.domain == NULL) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?owner_id=%llu&count=%u", stranica.id, stranica.kolichestvo) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.domain != NULL) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?domain=%s&count=%u", stranica.domain, stranica.kolichestvo) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else {
		fprintf(stderr, "%s: неизвестная ошибка при формировании запроса, возможно неправильные данные страницы\n", nazvanie);
		return NULL;
	}
	
	return url;
}

char *poluchit_url_zaprosa_info_stranicy(struct Parametry stranica)
{
	//if ()
	static char url[72];
	
	if (stranica.type == true && stranica.domain == NULL) {
		if (sprintf(url, "https://api.vk.com/method/groups.getById?group_id=%llu", stranica.id) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.id == 0 && stranica.domain != NULL) {
		if (sprintf(url, "https://api.vk.com/method/groups.getById?group_id=%s", stranica.domain) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else {
		return NULL;
	}
	
	return url;
}
