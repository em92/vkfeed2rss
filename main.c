#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>

#include "zagruzka.h"
#include "zapros.h"
#include "feed2rss.h"
#include "info.h"

void pomosch(char **argv) // выводит помощь к программе
{
	fprintf(stdout, "Использование:\n"
		"\t-h - эта помощь\n"
		"\t-v - версия программы\n"
		"\t-i - id сообщества\n"
		"\t-s - id пользователя\n"
		"\t-d - домен страницы, напр. \"apiclub\"\n"
		"\t-k - количество записей, максимум 100, 20 по умолчанию\n"
		"\t-p - описание для RSS ленты\n"
		"\t-z - заголовок для RSS ленты\n"
		"\t-o - путь к записываемому файлу (не работает, вывод в stdout)\n"
		"\t-f - фильтр (в этой версии не работает)\n"
		"\n%s -i 147930146 -z \"Имя сообщества\" -p \"Описание сообщества\" -k 20\n", argv[0]);
}

void version() // выводит версию программы
{
	fprintf(stdout, "%s v%s - переводчик ленты сообществ ВКонтакте в RSS\nAPI v%s\n", nazvanie, VERSION, APIVERSION);
}

int main(int argc, char **argv)
{
	struct Parametry stranica; // заполнение формы запроса для API ВК
	stranica.domain = NULL; // необходимая очистка
	stranica.id = 0; // тоже
	stranica.filter = 0; // временно нерабочая функция
	stranica.kolichestvo = 20; // значение count по умолчанию, см. wall.get в документации к API VK
	
	char opisanie[128]; // описание ленты: название оригинальной группы, ссылка и т.д.
	char zagolovok[64]; // заголовок RSS ленты

	if (argc == 1) { // если нет аргументов, то выводить помощь
		pomosch(argv);
		return 0;
	}
	else { // если аргументы есть, то будут обрабатываться
		int c;
		while ((c = getopt(argc, argv, "hvi:s:d:z:p:k:f")) != -1) { // getopt как и обычно
			switch (c) {
				case 'h': // помощь
					pomosch(argv);
					return 0;
				case 'v': // вывод версии
					version();
					return 0;
				case 'p': // описание
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
				case 'i': // группа
					stranica.id = atoi(optarg);
					stranica.type = true;
					break;
				case 's': // пользователь
					stranica.id = atoi(optarg);
					stranica.type = false;
					break;
				case 'd':
					if (stranica.id == 0) // если id введён не был, то проверка домена
						stranica.domain = optarg;
					break;
				case 'k':
					if ((atoi(optarg) > 100) || (atoi(optarg) == 0)) {
						fprintf(stderr, "%s: количество записей на страницу должно быть не больше 100 и не меньше 1, выбрано значение по умолчанию\n", nazvanie);
						stranica.kolichestvo = 20; // костыль
					}
					else stranica.kolichestvo = atoi(optarg);
					break;
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

	osnova_rss(zagolovok, opisanie, stranica); // сделать основу для RSS ленты

	obrabotka(lenta, stranica.kolichestvo); // обработать ленту для RSS
	
	return 0;
}
