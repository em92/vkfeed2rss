#include <stdio.h>
#include <string.h>

#include "zapros.h"
#include "info.h"

char *poluchit_url_zaprosa_lenty(struct Parametry stranica)
{
	static char url[72];
		
	if (stranica.type == true && stranica.domain == NULL) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?owner_id=-%llu&count=%u&v=%s", stranica.id, stranica.kolichestvo, APIVERSION) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.type == false && stranica.domain == NULL) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?owner_id=%llu&count=%u&v=%s", stranica.id, stranica.kolichestvo, APIVERSION) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.domain != NULL) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?domain=%s&count=%u&v=%s", stranica.domain, stranica.kolichestvo, APIVERSION) < 0) {
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
	static char url[72 + sizeof(stranica.domain)];
	
	if (stranica.domain != NULL) { // для доменов
		if (sprintf(url, "https://api.vk.com/method/groups.getById?group_id=%s&fields=description&v=%s", stranica.domain, APIVERSION) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.type == true && stranica.domain == NULL) { // для id групп
		if (sprintf(url, "https://api.vk.com/method/groups.getById?group_id=%llu&fields=description&v=%s", stranica.id, APIVERSION) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else if (stranica.type == false && stranica.domain == NULL) { // для id пользователей
		if (sprintf(url, "https://api.vk.com/method/users.get?user_ids=%llu&v=%s", stranica.id, APIVERSION) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else {
		return NULL;
	}
	
	return url;
}
