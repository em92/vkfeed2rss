#include <stdio.h>
#include <string.h>

#include "zapros.h"
#include "info.h"

char *poluchit_url_zaprosa(struct Parametry stranica)
{
	static char url[ sizeof("https://api.vk.com/method/wall.get?owner_id=count=filter=") + 12 + 9 + 3 + 1 ];
	
	if (stranica.tip == true) {
		if (sprintf(url, "https://api.vk.com/method/wall.get?owner_id=-%llu&count=%u", stranica.id, stranica.kolichestvo) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	else {
		if (sprintf(url, "https://api.vk.com/method/wall.get?owner_id=%llu&count=%u", stranica.id, stranica.kolichestvo) < 0) {
			fprintf(stderr, "%s: sprintf() error\n", nazvanie);
			return NULL;
		}
	}
	
	return url;
}
