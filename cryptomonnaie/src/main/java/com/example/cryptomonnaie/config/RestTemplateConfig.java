package com.example.cryptomonnaie.config;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.client.RestTemplate;

@Configuration
public class RestTemplateConfig {

    // Créer un bean RestTemplate pour effectuer les appels HTTP
    @Bean
    public RestTemplate restTemplate() {
        return new RestTemplate();
    }
}
