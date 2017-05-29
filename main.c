#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>

#include "zagruzka.h"
#include "zapros.h"
#include "feed2rss.h"
#include "info.h"

void pomosch(short version)
{
	if (version == 0) {
		fprintf(stderr, "Использование:\n"
		"\t-h - эта помощь\n"
		"\t-v - версия программы\n"
		"\t-i - id сообщества или страницы, обязательно должен быть числовым\n"
		"\t-t - тип: group - сообщество, page - страница\n"
		"\t-d - описание для RSS ленты\n"
		"\t-z - заголовок для RSS ленты\n"
		"\t-k - количество записей, максимум 100, 20 по умолчанию\n"
		"\t-f - фильтр (в этой версии не работает)\n"
		"\nПример: vkfeed2rss -i 115930352 -t group -z \"Обычное имя сообщества\" -d \"Хорошее описание\" -k 20\n"
		"RSS лента выводится в stdout.\n");
	}
	else {
		fprintf(stderr, "%s v%s - переводчик ленты сообществ ВКонтакте в RSS\n", nazvanie, VERSION);
	}
	exit(0);
}

int main(int argc, char **argv)
{
	struct Parametry stranica; // заполнение формы запроса
	stranica.filter = 0;
	
	char opisanie[128]; // описание ленты: название оригинальной группы, ссылка и т.д.
	char zagolovok[64];

	if (argc == 1) pomosch(0);
	else {
		int c;
		while ((c = getopt(argc, argv, "hvi:t:d:z:k:f")) != -1) {
			switch (c) {
				case 'h':
					pomosch(0);
					break;
				case 'v':
					pomosch(1);
					break;
				case 'd': // описание
					if (sizeof(optarg) > sizeof(opisanie)) {
						if (realloc(opisanie, sizeof(optarg) + (sizeof(optarg) - sizeof(opisanie)) + 1) == NULL) {
							fprintf(stderr, "%s: ошибка при реаллокации памяти\n", nazvanie);
							return -1;
						}
					}
					strcpy(opisanie, optarg);
					break;
				case 'z': // заголовок
					if (sizeof(optarg) > sizeof(zagolovok)) {
						if (realloc(zagolovok, sizeof(optarg) + (sizeof(optarg) - sizeof(zagolovok)) + 1) == NULL) {
							fprintf(stderr, "%s: ошибка при реаллокации памяти\n", nazvanie);
							return -1;
						}
					}
					strcpy(zagolovok, optarg);
					break;
				case 'i': // id
					stranica.id = atoi(optarg);
					break;
				case 't': // тип группы
					if (strcmp(optarg, "group") == 0)
						stranica.tip = true;
					else if (strcmp(optarg, "page") == 0)
						stranica.tip = false;
					else {
						fprintf(stderr, "%s: неправильный аргумент типа, возможные: group, page\n", nazvanie);
						return -1;
					}
				case 'k':
					//~ if (optarg > 100 || optarg < 1) {
						//~ fprintf(stderr, "%s: количество записей на страницу должно быть не больше 100 и не меньше 1\n", nazvanie);
						//~ return -1;
					//~ }
					//~ else stranica.kolichestvo = optarg;
					stranica.kolichestvo = 20; // костыль
			}
		}
	}
	
	// сейчас мы получим JSON вывод стены и потом будем его парсить
	
	char *url_zaprosa = poluchit_url_zaprosa(stranica);
	if (url_zaprosa == NULL) { // обработка ошибок
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}
	
	char *lenta = zagruzka_lenty(url_zaprosa);
	if (lenta == NULL) { // обработка ошибок
		fprintf(stderr, "%s: произошла непредвиденная ошибка при обращении к API ВКонтакте\n", nazvanie);
		return -1;
	}

	osnova_rss(zagolovok, opisanie, stranica.id, stranica.tip);

	obrabotka(lenta, stranica.kolichestvo);
	
	return 0;
}
