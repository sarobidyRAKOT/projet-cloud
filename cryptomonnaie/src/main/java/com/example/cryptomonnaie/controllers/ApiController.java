package com.example.cryptomonnaie.controllers;

import com.example.cryptomonnaie.services.ApiService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;

@Controller
public class ApiController {

    private final ApiService apiService;

    @Autowired
    public ApiController(ApiService apiService) {
        this.apiService = apiService;
    }

    // Page d'inscription
    @GetMapping("/register")
    public String showRegisterPage() {
        return "register";
    }

    // Soumettre l'inscription
    @PostMapping("/register")
    public String registerUser(@RequestParam String nom,
                               @RequestParam String prenom,
                               @RequestParam String email,
                               @RequestParam String password,
                               Model model) {
        String symfonyApiUrl = "http://localhost:8000"; // URL de l'API Symfony
        String response = apiService.registerUser(symfonyApiUrl, nom, prenom, email, password);
        
        model.addAttribute("successMessage", response);
        return "register";
    }

    // Page de connexion
    @GetMapping("/login")
    public String showLoginPage() {
        return "login";
    }

    // Soumettre la connexion
    @PostMapping("/login")
    public String loginUser(@RequestParam String email,
                            @RequestParam String password,
                            @RequestParam int code_pin,
                            Model model) {
        String symfonyApiUrl = "http://localhost:8000"; // URL de l'API Symfony
        String response = apiService.loginUser(symfonyApiUrl, email, password, code_pin);

        // Afficher la réponse dans la vue
        if (response.contains("error")) {
            model.addAttribute("errorMessage", response);
        } else {
            model.addAttribute("successMessage", "Connexion réussie");
        }
        return "login";
    }
}
