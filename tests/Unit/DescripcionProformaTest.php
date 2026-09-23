<?php

namespace Tests\Unit;

use App\Support\DescripcionProforma;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias puras (sin bootstrap de Laravel) del formateador de
 * descripciones. Cubren el happy path, los casos borde y los falsos positivos
 * documentados en el propio helper.
 */
class DescripcionProformaTest extends TestCase
{
    // ==================================================================
    // HAPPY PATH
    // ==================================================================

    public function test_la_primera_linea_se_renderiza_como_titulo_en_negrita(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma('Instalación de riego');

        $this->assertStringStartsWith('<strong', $html);
        $this->assertStringContainsString('desc-titulo', $html);
        $this->assertStringContainsString('Instalación de riego</strong>', $html);
    }

    public function test_las_claves_con_vineta_se_renderizan_en_negrita_con_su_valor(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma(
            "Sistema de riego\n• Sección Fija: estructura 2x2\nDimensiones Generales: 12 m"
        );

        $this->assertStringContainsString('<b class="font-bold text-gray-900 desc-clave">• Sección Fija:</b> estructura 2x2', $html);
        $this->assertStringContainsString('<b class="font-bold text-gray-900 desc-clave">Dimensiones Generales:</b> 12 m', $html);
    }

    public function test_los_saltos_de_linea_se_convierten_en_br(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo\nDetalle uno\nDetalle dos");

        $this->assertSame(2, substr_count($html, '<br>'));
    }

    public function test_titulo_y_detalle_separan_la_primera_linea_del_resto(): void
    {
        [$titulo, $detalle] = DescripcionProforma::tituloYDetalle("Titulo\nLinea 1\nLinea 2");

        $this->assertSame('Titulo', $titulo);
        $this->assertSame("Linea 1\nLinea 2", $detalle);
    }

    // ==================================================================
    // CASOS BORDE
    // ==================================================================

    #[DataProvider('textosVacios')]
    public function test_devuelve_cadena_vacia_y_no_falla_con_entradas_vacias(?string $texto): void
    {
        $this->assertSame('', DescripcionProforma::formatDescripcionProforma($texto));

        [$titulo, $detalle] = DescripcionProforma::tituloYDetalle($texto);
        $this->assertSame('', $titulo);
        $this->assertSame('', $detalle);
    }

    public static function textosVacios(): array
    {
        return [
            'nulo' => [null],
            'cadena vacía' => [''],
            'solo espacios' => ['     '],
            'solo saltos de línea' => ["\n\n\n"],
        ];
    }

    public function test_normaliza_saltos_crlf_y_cr(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo\r\nClave: valor\rDetalle");

        $this->assertStringContainsString('Titulo</strong>', $html);
        $this->assertStringContainsString('<b class="font-bold text-gray-900 desc-clave">Clave:</b> valor', $html);
        $this->assertStringContainsString('<br>Detalle', $html);
    }

    public function test_las_lineas_en_blanco_no_generan_br_sueltos(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo\n\n\nClave: valor");

        $this->assertSame(1, substr_count($html, '<br>'));
    }

    public function test_las_vinetas_sin_clave_se_mantienen_con_su_texto(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo\n• Punto suelto sin clave");

        $this->assertStringContainsString('<br>• Punto suelto sin clave', $html);
        $this->assertStringNotContainsString('desc-clave', $html);
    }

    public function test_una_clave_de_la_longitud_maxima_si_se_marca_pero_una_mas_larga_no(): void
    {
        $limite = str_repeat('A', DescripcionProforma::MAX_LONGITUD_CLAVE);

        $this->assertStringContainsString('desc-clave', DescripcionProforma::formatDescripcionProforma("T\n{$limite}: valor"));
        $this->assertStringNotContainsString('desc-clave', DescripcionProforma::formatDescripcionProforma("T\n{$limite}X: valor"));
    }

    // ==================================================================
    // SEGURIDAD: el texto del usuario nunca debe inyectar HTML
    // ==================================================================

    public function test_escapa_el_html_del_usuario_en_titulo_detalle_y_claves(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma(
            "Titulo <script>alert(1)</script>\nClave: <img src=x onerror=alert(2)>"
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
    }

    public function test_escapa_comillas_y_ampersand_para_no_romper_atributos(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo \"x\" & 'y'");

        $this->assertStringContainsString('&quot;x&quot;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('&#039;y&#039;', $html);
    }

    // ==================================================================
    // FALSOS POSITIVOS (documentados en el propio helper)
    // ==================================================================

    public function test_no_confunde_una_hora_con_una_clave(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Servicio de riego\nHorario 10:30 am en sitio");

        $this->assertStringNotContainsString('desc-clave', $html);
        $this->assertStringContainsString('<br>Horario 10:30 am en sitio', $html);
    }

    public function test_no_confunde_una_url_con_una_clave(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo\nVer https://google.com/foo: bar");

        $this->assertStringNotContainsString('desc-clave', $html);
        $this->assertStringContainsString('<br>Ver https://google.com/foo: bar', $html);
    }

    public function test_una_clave_sin_valor_se_marca_en_negrita(): void
    {
        $html = DescripcionProforma::formatDescripcionProforma("Titulo\n• Sección Fija:");

        $this->assertStringContainsString('<b class="font-bold text-gray-900 desc-clave">• Sección Fija:</b>', $html);
    }
}
