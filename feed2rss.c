#include <stdio.h>
#include <string.h>
#include <jansson.h>
#include <time.h>

#include "info.h"
#include "zapros.h"

int printf_rss(const char *str) // вывод строки с заменой всех "<br>" на XML аналог, нужен для вывода постов
{
	for (unsigned i = 0; i < strlen(str); i++) {
		unsigned buff = i;
		switch (str[i]) {
			case '\n': // обработка <br>
				printf("&lt;br&gt;");
				i++;
				break;
			case '<':
				printf("&lt;");
				i++;
				break;
			case '>':
				printf("&gt;");
				i++;
				break;
			case '&':
				printf("&amp;");
				i++;
				break;
			case '\'':
				printf("&apos;");
				i++;
				break;
			case '\"':
				printf("&quot;");
				i++;
				break;
		}
			
		if (i > buff)
			i--;
		else
			putchar(str[i]);
	}
	return 0;
}

char *vremja(time_t epoch) // время, аналог команды date
// epoch - unix-time для конвертации, если нужно получить текущее время, укажите 0
{
	time_t rawtime;
  struct tm * timeinfo;
  
  if (epoch == 0) time (&rawtime);
  else rawtime = epoch;
  timeinfo = gmtime (&rawtime);
  char *buff = asctime (timeinfo);
  for (unsigned i = 0; ; i++) { // убрать \n на конце строки
		if (buff[i] == '\0') {
			buff[i-1] = '\0';
			return buff;
		}
	}
}

char *poluchit_zagolovok(struct Parametry stranica)
{
	json_t *root;
	json_error_t error;
	
	root = json_loads(stranica.info, 0, &error);
	if (!root) {
		fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
		return NULL;
	}
	
	json_t *response = json_object_get(root, "response"); // получение самого ответа
	if(!response) {
		fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
		return NULL;
	}
	
	static char buff[64];
	json_t *array = json_array_get(response, 0);
	if (stranica.type == GROUP || stranica.domain != NULL) { // если дан id группы
		json_t *name = json_object_get(array, "name");
		sprintf(buff, "%s", json_string_value(name));
	}
	else if (stranica.type == PAGE) { // если страница
		json_t *first_name = json_object_get(array, "first_name");
		json_t *last_name  = json_object_get(array, "last_name");
		sprintf(buff, "%s %s", json_string_value(first_name), json_string_value(last_name));
	}
	else { 
		fprintf(stderr, "%s: при обработке имени сообщества или страницы произошла ошибка\n", nazvanie);
		return NULL;
	}
	
	if (stranica.domain != NULL) {
		
	}
	
	return buff;
}

char *poluchit_opisanie(struct Parametry stranica)
{
	json_t *root;
	json_error_t error;
	
	root = json_loads(stranica.info, 0, &error);
	if (!root) {
		fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
		return NULL;
	}
	
	json_t *response = json_object_get(root, "response"); // получение самого ответа
	if(!response) {
		fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
		return NULL;
	}
	
	static char buff[64];
	json_t *array = json_array_get(response, 0);
	if (stranica.type == GROUP || stranica.domain != NULL) {
		json_t *name = json_object_get(array, "description");
		sprintf(buff, "%s", json_string_value(name));
	}
	else if (stranica.type == PAGE) {
		return "Страница пользователя";
	}
	
	return buff;
}

int osnova_rss(struct Parametry stranica) // создаёт основу для RSS ленты
{
	printf("<?xml version=\"%s\"?>\n", XMLVERSION);
	printf("<rss version=\"%s\">\n", RSSVERSION);
	printf("\t<channel>\n");
	printf("\t\t<title>%s</title>\n", stranica.zagolovok);
	if (stranica.domain != NULL) printf("\t\t<link>https://vk.com/%s</link>\n", stranica.domain);
	else if (stranica.type == false) printf("\t\t<link>https://vk.com/id%llu</link>\n", stranica.id);
	else if (stranica.type == true) printf("\t\t<link>https://vk.com/club%llu</link>\n", stranica.id);
	else {
		fprintf(stderr, "%s: произошла ошибка при начальном формировании RSS ленты, неверные данные страницы\n", nazvanie);
		return -1;
	}
	printf("\t\t<description>");
	printf_rss(stranica.opisanie);
	printf("</description>\n");
	printf("\t\t<pubDate>%s</pubDate>\n", vremja(0));
	printf("\t\t<generator>%s v%s</generator>\n", nazvanie, VERSION);
	return 0;
}

int obrabotka(struct Parametry stranica)
{
	json_t *root;
	json_error_t error;
	
	root = json_loads(stranica.lenta, 0, &error); // загрузка JSON ответа для ленты
	if (!root) {
		fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
		return -1;
	}

	json_t *response = json_object_get(root, "response"); // получение самого ответа
	if(!response) {
		fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
		return -1;
	}

	for (unsigned i = 0; i < stranica.kolichestvo; i++) { // вывод всех полученных записей в XML
		json_t *count = json_object_get(response, "count");
		json_t *items = json_object_get(response, "items");
		json_t *post = json_array_get(items, i);
		if (!count || !items) {
			fprintf(stderr, "%s: произошла ошибка при обработке ленты: %s: %s\n", nazvanie, error.text, error.source);
			return -1;
		}
		json_t *id = json_object_get(post, "id");
		json_t *date = json_object_get(post, "date");
		json_t *text = json_object_get(post, "text");
		json_t *attachments = json_object_get(post, "attachments");
		json_t *is_pinned = json_object_get(post, "is_pinned");
		
		if (json_string_value(text) == NULL) break; 
		else { // вывод записи
			printf("\t\t<item>\n");
			if (json_integer_value(is_pinned) == 0)
				printf("\t\t\t<title>Запись %lli</title>\n", json_integer_value(id));
			else if (json_integer_value(is_pinned) == 1)
				printf("\t\t\t<title>Прикреплённая запись %lli</title>\n", json_integer_value(id));
			printf("\t\t\t<description>");
			if (printf_rss(json_string_value(text)) == -1) return -1; // эта строка, та, что выше и та, что ниже - сам пост, функция printf_nobr заменяет некоторые символы (которые может не понять читалка RSS) на помятные XML аналоги
			// запись прикреплённых изображений кроме первой в описание
			for (unsigned o = 0; ; o++) {
				json_t *attachment = json_array_get(attachments, o); // картинка
				json_t *photoarray = json_object_get(attachment, "photo");
				json_t *image 		 = json_object_get(photoarray, "photo_604");
				if (json_is_string(image) == 1) printf("&lt;img src=\"%s\"&gt;\n", json_string_value(image));
				else break;
			}
			printf("</description>\n");
			printf("\t\t\t<pubDate>%s</pubDate>\n", vremja(json_integer_value(date)));
			if (stranica.domain != NULL) printf("\t\t<link>https://vk.com/%s", stranica.domain);
			else if (stranica.type == false) printf("\t\t<link>https://vk.com/id%llu", stranica.id);
			else if (stranica.type == true) printf("\t\t<link>https://vk.com/club%llu", stranica.id);
			else {
				fprintf(stderr, "%s: произошла ошибка при начальном формировании RSS ленты, неверные данные страницы\n", nazvanie);
				return -1;
			}
			printf("?w=wall-%llu_%lli</link>\n", stranica.id, json_integer_value(id));
			
			printf("\t\t</item>\n");
		}
	}
	printf("\t</channel>\n");
	printf("</rss>\n");

	return 0;
}
