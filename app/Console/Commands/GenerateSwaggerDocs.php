<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator;

class GenerateSwaggerDocs extends Command
{
    protected $signature = 'swagger:generate-docs';
    protected $description = 'Generate Swagger API documentation (suppresses PathItem warnings)';

    public function handle()
    {
        $this->info('Generating Swagger documentation...');

        // Suppress PathItem warnings
        set_error_handler(function ($errno, $errstr) {
            if (strpos($errstr, 'Required @OA\PathItem() not found') !== false) {
                // Suppress this specific warning
                return true;
            }
            // Let other errors/warnings through
            return false;
        });

        try {
            $annotationPaths = config('l5-swagger.documentations.default.paths.annotations');
            $outputPath = config('l5-swagger.defaults.paths.docs') . '/api-docs.json';

            $openapi = Generator::scan($annotationPaths);
            
            $jsonContent = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            file_put_contents($outputPath, $jsonContent);

            $this->info('✓ Swagger documentation generated successfully!');
            $this->info('Location: ' . $outputPath);
            
            restore_error_handler();
            return 0;
        } catch (\Exception $e) {
            restore_error_handler();
            $this->error('Failed to generate Swagger documentation');
            $this->error($e->getMessage());
            return 1;
        }
    }
}

