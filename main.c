#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <getopt.h>

#include "zagruzka.h"
#include "zapros.h"
#include "feed2rss.h"
#include "info.h"

void pomosch(char *zapusk) // выводит помощь к программе
{
	fprintf(stdout, "Использование:\n"
		"\t-h - эта помощь\n"
		"\t-v - версия программы\n"
		"\t-i - id сообщества\n"
		"\t-s - id пользователя\n"
		"\t-d - домен страницы, напр. \"apiclub\"\n"
		"\t-k - количество записей, максимум 100, 20 по умолчанию\n"
		"\t-f - фильтр (в этой версии не работает)\n"
		"\n%s -d apiclub\n", zapusk);
}

void version() // выводит версию программы
{
	fprintf(stdout, "%s v%s - переводчик ленты сообществ ВКонтакте в RSS\nAPI v%s\n", nazvanie, VERSION, APIVERSION);
}

int main(int argc, char **argv)
{
	struct Parametry stranica; // заполнение формы запроса для API ВК
	stranica.domain = NULL; // необходимая очистка
	stranica.zagolovok = NULL;
	stranica.opisanie = NULL;
	stranica.lenta = NULL;
	stranica.info = NULL;
	stranica.id = 0; // тоже
	stranica.filter = 0; // временно нерабочая функция
	stranica.kolichestvo = 20; // значение count по умолчанию, см. wall.get в документации к API VK

	if (argc == 1) { // если нет аргументов, то выводить помощь
		pomosch(argv[0]);
		return 0;
	}
	else { // если аргументы есть, то будут обрабатываться
		int c;
		while ((c = getopt(argc, argv, "hvi:s:d:k:f")) != -1) { // getopt как и обычно
			switch (c) {
				case 'h': // помощь
					pomosch(argv[0]);
					return 0;
				case 'v': // вывод версии
					version();
					return 0;
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
					if (atoi(optarg) > 100 || atoi(optarg) < 0) {
						fprintf(stderr, "%s: количество записей на страницу должно быть не больше 100 и не меньше 0, выбрано значение по умолчанию\n", nazvanie);
						stranica.kolichestvo = 20; // костыль
					}
					else stranica.kolichestvo = atoi(optarg);
					break;
			}
		}
	}
	
	// сейчас мы получим JSON вывод стены и потом будем его парсить
	
	char *url_zaprosa_lenty = poluchit_url_zaprosa_lenty(stranica); // создать ссылку запроса для API чтобы получить ленту
	if (url_zaprosa_lenty == NULL) {
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}
	char *url_zaprosa_info_stranicy = poluchit_url_zaprosa_info_stranicy(stranica); // создать ссылку запроса для API чтобы получить информацию о странице
	if (url_zaprosa_info_stranicy == NULL) {
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}

	stranica.lenta = zagruzka_lenty(url_zaprosa_lenty); // загружаем ленту
	if (stranica.lenta == NULL) {
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}
	
	stranica.info = zagruzka_lenty(url_zaprosa_info_stranicy); // загружаем информацию о странице
	if (stranica.info == NULL) {
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}

	stranica.zagolovok = poluchit_zagolovok(stranica); // полученное stranica.info надо обработать и записать данные
	if (stranica.zagolovok == NULL) {
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}
	
	stranica.opisanie  = poluchit_opisanie(stranica);
	if (stranica.opisanie == NULL) {
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}

	if (osnova_rss(stranica)) { // сделать основу для RSS ленты
		fprintf(stderr, "%s: произошла непредвиденная ошибка при образовании запроса\n", nazvanie);
		return -1;
	}

	obrabotka(stranica); // обработать ленту для RSS
	
	return 0;
}
