<?php

namespace App\Http\Controllers;

/**
 * Class Controller
 * 
 * Base controller class for all API controllers
 * 
 * @OA\Info(
 *     title="Academic Finder Backend API",
 *     version="1.0.0",
 *     description="API documentation for Academic Finder Backend - Admin and Company management system",
 *     @OA\Contact(
 *         email="support@academicfinder.com",
 *         name="Academic Finder Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://acdmicback.twindix.com",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your bearer token in the format: Bearer {token}"
 * )
 */
abstract class Controller
{
    //
}
