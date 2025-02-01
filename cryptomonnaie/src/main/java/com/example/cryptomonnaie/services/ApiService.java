package com.example.cryptomonnaie.services;

import org.springframework.http.*;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestTemplate;

@Service
public class ApiService {

    private final RestTemplate restTemplate;

    public ApiService(RestTemplate restTemplate) {
        this.restTemplate = restTemplate;
    }

    // Méthode pour l'inscription
    public String registerUser(String url, String nom, String prenom, String email, String password) {
        String apiUrl = url + "/api/register";
        
        // Créer le payload en JSON
        String jsonPayload = String.format("{\"nom\":\"%s\", \"prenom\":\"%s\", \"email\":\"%s\", \"password\":\"%s\"}", nom, prenom, email, password);

        // Créer l'entité HTTP avec les headers et le corps
        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);
        HttpEntity<String> entity = new HttpEntity<>(jsonPayload, headers);

        // Faire la requête HTTP POST à l'API Symfony
        ResponseEntity<String> response = restTemplate.exchange(apiUrl, HttpMethod.POST, entity, String.class);
        return response.getBody();
    }

    // Méthode pour la connexion
    public String loginUser(String url, String email, String password, int codePin) {
        String apiUrl = url + "/api/login/first";
        
        // Créer le payload en JSON
        String jsonPayload = String.format("{\"email\":\"%s\", \"password\":\"%s\", \"code_pin\":\"%s\"}", email, password, codePin);

        // Créer l'entité HTTP avec les headers et le corps
        HttpHeaders headers = new HttpHeaders();
        headers.setContentType(MediaType.APPLICATION_JSON);
        HttpEntity<String> entity = new HttpEntity<>(jsonPayload, headers);

        // Faire la requête HTTP POST à l'API Symfony
        ResponseEntity<String> response = restTemplate.exchange(apiUrl, HttpMethod.POST, entity, String.class);
        return response.getBody();
    }
}
