#include <curl/curl.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#include "info.h"

struct Memory {
	char *memory;
	size_t size;
};

size_t write_callback(char *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t realsize = size * nmemb;
	
	struct Memory *mem = (struct Memory *)userdata;
	
	mem->memory = realloc(mem->memory, mem->size + realsize + 1);
	if (mem->memory == NULL) {
		fprintf(stderr, "%s: нехватка памяти\n", nazvanie);
		exit(-1);
	}
	
	memcpy(&(mem->memory[mem->size]), ptr, realsize);
	mem->size += realsize;
	mem->memory[mem->size] = '\0';
	
	return realsize;
}

char *zagruzka_lenty(char *zapros)
{
	struct Memory feedchunk;
	
	feedchunk.memory = malloc(1);
	feedchunk.size = 0;
	
	curl_global_init(CURL_GLOBAL_ALL);
	
	CURL *curl = curl_easy_init();
	if (!curl) {
		fprintf(stderr, "%s: curl easy handle error\n", nazvanie);
		return NULL;
	}
	
	CURLcode res;
	curl_easy_setopt(curl, CURLOPT_URL, zapros);
	curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, write_callback);
	curl_easy_setopt(curl, CURLOPT_WRITEDATA, (void *)&feedchunk);
	curl_easy_setopt(curl, CURLOPT_USERAGENT, "libcurl-agent/1.0");
	res = curl_easy_perform(curl);
	if (res != CURLE_OK) {
		fprintf(stderr, "%s: ошибка при загрузке данных\n", nazvanie);
		return NULL;
	}
	
	curl_easy_cleanup(curl);
	//free(feedchunk.memory);
	curl_global_cleanup();
	
	return feedchunk.memory;
}
