#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>

#include "zagruzka.h"
#include "zapros.h"
#include "feed2rss.h"
#include "info.h"

void pomosch() // выводит помощь к программе
{
	fprintf(stdout, "Использование:\n"
		"\t-h - эта помощь\n"
		"\t-v - версия программы\n"
		"\t-i - id сообщества или страницы, обязательно должен быть числовым\n"
		"\t-t - тип: group - сообщество, page - страница\n"
		"\t-d - описание для RSS ленты\n"
		"\t-z - заголовок для RSS ленты\n"
		"\t-o - путь к записываемому файлу (не работает, вывод в stdout)\n"
		"\t-k - количество записей, максимум 100, 20 по умолчанию\n"
		"\t-f - фильтр (в этой версии не работает)\n"
		"\nПример: vkfeed2rss -i 147930146 -t group -z \"Обычное имя сообщества\" -d \"Хорошее описание\" -k 20\n");
}

void version() // выводит версию программы
{
	fprintf(stdout, "%s v%s - переводчик ленты сообществ ВКонтакте в RSS\nAPI v%s\n", nazvanie, VERSION, APIVERSION);
}

int main(int argc, char **argv)
{
	struct Parametry stranica; // заполнение формы запроса для API ВК
	stranica.filter = 0; // временно нерабочая функция
	
	char opisanie[128]; // описание ленты: название оригинальной группы, ссылка и т.д.
	char zagolovok[64]; // заголовок RSS ленты

	if (argc == 1) { // если нет аргументов, то выводить помощь
		pomosch();
		return 0;
	}
	else { // если аргументы есть, то будут обрабатываться
		int c;
		while ((c = getopt(argc, argv, "hvi:t:d:z:k:f")) != -1) { // getopt как и обычно
			switch (c) {
				case 'h': // помощь
					pomosch();
					return 0;
				case 'v': // вывод версии
					version();
					return 0;
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
					if ((atoi(optarg) > 100) || (atoi(optarg) < 1)) {
						fprintf(stderr, "%s: количество записей на страницу должно быть не больше 100 и не меньше 1, выбрано значение по умолчанию\n", nazvanie);
						stranica.kolichestvo = 20; // костыль
					}
					else stranica.kolichestvo = atoi(optarg);
			}
		}
	}
	
	// сейчас мы получим JSON вывод стены и потом будем его парсить
	
	char *url_zaprosa = poluchit_url_zaprosa(stranica); // создать ссылку запроса для API
	if (url_zaprosa == NULL) { // обработка ошибок
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}
	
	char *lenta = zagruzka_lenty(url_zaprosa); // парсим
	if (lenta == NULL) { // обработка ошибок
		fprintf(stderr, "%s: произошла непредвиденная ошибка при обращении к API ВКонтакте\n", nazvanie);
		return -1;
	}

	osnova_rss(zagolovok, opisanie, stranica.id, stranica.tip); // сделать основу для RSS ленты

	obrabotka(lenta, stranica.kolichestvo); // обработать ленту для RSS
	
	return 0;
}
