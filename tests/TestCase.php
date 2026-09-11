<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Facades\Tenancy;

abstract class TestCase extends BaseTestCase
{
    /**
     * Connexions incluses dans les transactions de RefreshDatabase.
     *
     * On épingle la connexion centrale `sqlite` pour éviter que
     * RefreshDatabase ne tente d'annuler la connexion `tenant` après
     * un test qui appelle tenancy()->initialize().
     *
     * @var string[]
     */
    protected $connectionsToTransact = ['sqlite'];

    /**
     * Réinitialise le contexte tenant après chaque test pour restaurer
     * la connexion par défaut avant le teardown de RefreshDatabase.
     *
     * Sans cet appel, database.default reste sur `tenant` après un test
     * qui appelle tenancy()->initialize(), ce qui casse le prochain test.
     */
    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * Prépare l'URL en tenant compte de l'en-tête Host éventuel.
     *
     * Symfony\Request::create() extrait le host depuis l'URL et écrase
     * HTTP_HOST avec la valeur de l'URL, ignorant l'en-tête Host passé
     * via withHeaders(['Host' => '...']). On surcharge cette méthode pour
     * construire l'URL à partir du Host header si celui-ci est présent,
     * garantissant que DomainTenantResolver reçoit le bon domaine.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prepareUrlForRequest($uri): string
    {
        $uri = (string) $uri;

        // Si un en-tête Host est défini, on construit l'URL à partir de ce host
        // afin que Symfony::create() extraie le bon HOST et non celui de APP_URL.
        if (str_starts_with($uri, '/') && isset($this->defaultHeaders['Host'])) {
            $host = $this->defaultHeaders['Host'];

            return 'http://' . $host . $uri;
        }

        return parent::prepareUrlForRequest($uri);
    }
}
