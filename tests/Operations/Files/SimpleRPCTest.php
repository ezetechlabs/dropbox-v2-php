<?php
    /**
 * Copyright (c) 2016 Alorel, https://github.com/Alorel
 * Licenced under MIT: https://github.com/Alorel/dropbox-v2-php/blob/master/LICENSE
 */

    namespace Alorel\Dropbox\Operations\Files;

    use Alorel\Dropbox\Operation\Files\Delete;
    use Alorel\Dropbox\Operation\Files\GetMetadata;
    use Alorel\Dropbox\Operation\Files\PermanentlyDelete;
    use Alorel\Dropbox\Operation\Files\Upload;
    use Alorel\Dropbox\OperationKind\SingleArgumentRPCOperation;
    use Alorel\Dropbox\Options\Builder\GetMetadataOptions;
    use Alorel\Dropbox\Options\Builder\UploadOptions;
    use Alorel\Dropbox\Options\Options;
    use Alorel\Dropbox\Test\NameGenerator;
    use GuzzleHttp\Exception\ClientException;

    class SimpleRPCTest extends \PHPUnit_Framework_TestCase {

        use NameGenerator;

        function testGetMetadata() {
            $filename = self::genFileName();
            $dt = new \DateTime('2001-01-01');
            (new Upload())->raw($filename, __METHOD__, (new UploadOptions())->setClientModified($dt));
            $meta = json_decode(
                (new GetMetadata())->raw(
                    $filename,
                    (new GetMetadataOptions())->setIncludeHasExplicitSharedMembers(true)
                )->getBody()->getContents(),
                true
            );

            $this->assertEquals('file', $meta['.tag']);
            $this->assertEquals($filename, $meta['path_display']);
            $this->assertEquals(strtolower($filename), $meta['path_lower']);
            $this->assertEquals($dt->format(Options::DATETIME_FORMAT), $meta['client_modified']);
            $this->assertEquals(strlen(__METHOD__), $meta['size']);
            $this->assertTrue(is_bool($meta['has_explicit_shared_members']));
        }

        /** @dataProvider providerDelete */
        function testDelete($class) {
            $filename = self::genFileName();
            (new Upload())->raw($filename, '.');
            $options = (new GetMetadataOptions())->setIncludeDeleted(true);
            $meta = new GetMetadata();

            $this->assertEquals('file',
                                json_decode(
                                    $meta->raw($filename, $options)->getBody()->getContents(),
                                    true
                                )['.tag']);

            /** @var SingleArgumentRPCOperation $obj */
            $obj = new $class();
            if ($class === Delete::class) {
                $obj->raw($filename);
                $this->assertEquals('deleted',
                                    json_decode(
                                        $meta->raw($filename, $options)->getBody()->getContents(),
                                        true
                                    )['.tag']);
            } else {
                try {
                    $obj->raw($filename);

                    $this->expectException(ClientException::class);
                    $this->expectExceptionCode(409);
                    $meta->raw($filename, $options)->getBody()->getContents();
                } catch (ClientException $e) {
                    $class = (new \ReflectionClass($class))->getShortName();
                    fwrite(STDERR,
                           PHP_EOL . 'Failed to ' . $class . ' (most likely due to API permissions): '
                           . $e->getMessage() . PHP_EOL);
                }
            }
        }

        function providerDelete() {
            return [
                Delete::class            => [Delete::class],
                PermanentlyDelete::class => [PermanentlyDelete::class]
            ];
        }
    }