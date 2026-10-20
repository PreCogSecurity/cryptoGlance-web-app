<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for issues the datafactor assessment flagged:
 * hardcoded credentials in source, and configuration that must come from
 * the environment instead of the repository.
 */
class SecretsRegressionTest extends TestCase {

    private function projectPhpFiles() {
        $root = dirname(__DIR__);
        $files = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            // Vendored dependencies and tests are out of scope.
            if (strpos($path, '/vendor/') !== false || strpos($path, '/tests/') !== false) {
                continue;
            }
            $files[] = $file->getPathname();
        }
        return array_unique($files);
    }

    public function testNoHardcodedApiKeyLiteralsInSource() {
        // Only pure quoted literals count; concatenated expressions are code,
        // not committed secrets.
        $pattern = "/((api[_]?key|password|secret)[A-Za-z_]*\\s*=>?\\s*)['\"][A-Za-z0-9+\\/_=-]{8,}['\"]/i";
        $offenders = array();
        foreach ($this->projectPhpFiles() as $file) {
            // Skip the Markdown parser and other vendored-in libraries.
            $base = basename($file);
            if (in_array($base, array('Markdown.php'), true)
                || strpos($file, 'Michelf') !== false
                || strpos($file, 'simplepie') !== false
                || strpos($file, 'class.phpmailer') !== false
                || strpos($file, 'changelog.php') !== false
            ) {
                continue;
            }
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }
            // Strip comments so documentation examples do not trip the scan.
            $source = preg_replace('#/\\*.*?\\*/#s', '', $source);
            $source = preg_replace('/\\s*#+[^\\n]*/', '', $source);
            $source = preg_replace('/\\s*\\/\\/[^\\n]*/', '', $source);
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($source, 0, $match[1]), "\n") + 1;
                    $offenders[] = basename($file) . ':' . $line . ' ' . $match[0];
                }
            }
        }
        $this->assertSame(
            array(),
            $offenders,
            "Hardcoded credential literals found in source:\n" . implode("\n", $offenders)
        );
    }

    public function testMobileMinerClassHasNoBakedInApplicationKey() {
        $source = file_get_contents(dirname(__DIR__) . '/includes/classes/mobileminer.php');
        $this->assertStringNotContainsString("'apiKey='", $source, 'MobileMiner must not embed a literal API key.');
        $this->assertStringContainsString('MOBILEMINER_API_KEY', $source, 'MobileMiner must read its key from the environment.');
    }

    public function testCurlCallVerifiesTlsCertificatesByDefault() {
        $source = file_get_contents(dirname(__DIR__) . '/includes/functions.php');
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', $source);
        $this->assertStringContainsString('CG_INSECURE_TLS', $source, 'TLS opt-out must be explicit and env-driven.');
        // The old insecure defaults must not come back.
        $this->assertStringNotContainsString('CURLOPT_SSL_VERIFYPEER, false)', $source);
        $this->assertStringNotContainsString('CURLOPT_SSL_VERIFYHOST, false)', $source);
    }

    public function testEnvLoaderPrefersRealEnvironmentOverDotEnvFile() {
        putenv('CG_TEST_ENV_VALUE=from-process');
        $tmp = tempnam(sys_get_temp_dir(), 'cgenv');
        file_put_contents($tmp, "CG_TEST_ENV_VALUE=from-file\nCG_TEST_ENV_OTHER=second\n");
        $imported = cg_load_env($tmp);
        unlink($tmp);

        $this->assertSame(1, $imported, 'Only the unset variable should be imported.');
        $this->assertSame('from-process', cg_env('CG_TEST_ENV_VALUE'));
        $this->assertSame('second', cg_env('CG_TEST_ENV_OTHER'));
        putenv('CG_TEST_ENV_VALUE');
        putenv('CG_TEST_ENV_OTHER');
    }

    public function testEnvHelpersCoerceTypesSafely() {
        putenv('CG_TEST_ENV_BOOL=1');
        putenv('CG_TEST_ENV_INT=30');
        $this->assertTrue(cg_env_bool('CG_TEST_ENV_BOOL', false));
        $this->assertSame(30, cg_env_int('CG_TEST_ENV_INT', 20, 1, 100));
        $this->assertSame(20, cg_env_int('CG_TEST_ENV_MISSING', 20, 1, 100));
        $this->assertFalse(cg_env_bool('CG_TEST_ENV_MISSING', false));
        putenv('CG_TEST_ENV_BOOL');
        putenv('CG_TEST_ENV_INT');
    }
}
