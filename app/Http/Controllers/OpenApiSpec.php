<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Academic Finder Backend API",
    description: "API documentation for Academic Finder Backend - Admin and Company management system",
    contact: new OA\Contact(
        name: "Academic Finder Support",
        email: "support@academicfinder.com"
    )
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Local Development Server"
)]
#[OA\Server(
    url: "https://acdmicback.twindix.com",
    description: "Production Server"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your bearer token in the format: Bearer {token}"
)]
class OpenApiSpec
{
    // This class exists solely for OpenAPI documentation
    // No implementation needed
}

