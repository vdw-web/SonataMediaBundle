<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Tests\Provider;

use Sonata\MediaBundle\Tests\Entity\Media;

class VimeoProviderTest extends \PHPUnit_Framework_TestCase
{

    public function getProvider()
    {
        $em = 1;

        $resizer = $this->getMock('Sonata\MediaBundle\Media\ResizerInterface', array('resize'));
        $resizer->expects($this->any())
            ->method('resize')
            ->will($this->returnValue(true));

        $adapter = $this->getMock('Gaufrette\Filesystem\Adapter');

        $file = $this->getMock('Gaufrette\Filesystem\File', array(), array($adapter));

        $filesystem = $this->getMock('Gaufrette\Filesystem\Filesystem', array('get'), array($adapter));
        $filesystem->expects($this->any())
            ->method('get')
            ->will($this->returnValue($file));

        $cdn = new \Sonata\MediaBundle\CDN\Server('/updoads/media');

        $provider = new \Sonata\MediaBundle\Provider\VimeoProvider('file', $em, $filesystem, $cdn);
        $provider->setResizer($resizer);
        
        return $provider;
    }

    public function testProvider()
    {

        $provider = $this->getProvider();

        $media = new Media;
        $media->setName('Blinky™');
        $media->setProviderName('vimeo');
        $media->setProviderReference('21216091');
        $media->setProviderMetadata(json_decode('{"type":"video","version":"1.0","provider_name":"Vimeo","provider_url":"http:\/\/vimeo.com\/","title":"Blinky\u2122","author_name":"Ruairi Robinson","author_url":"http:\/\/vimeo.com\/ruairirobinson","is_plus":"1","html":"<iframe src=\"http:\/\/player.vimeo.com\/video\/21216091\" width=\"1920\" height=\"1080\" frameborder=\"0\"><\/iframe>","width":"1920","height":"1080","duration":"771","description":"","thumbnail_url":"http:\/\/b.vimeocdn.com\/ts\/136\/375\/136375440_1280.jpg","thumbnail_width":1280,"thumbnail_height":720,"video_id":"21216091"}', true));
        $media->setId(10);

        $this->assertEquals('http://www.vimeo.com/21216091', $provider->getAbsolutePath($media), '::getAbsolutePath() return the correct path - id = 1');

        $media->setId(1023457);
        $this->assertEquals('http://b.vimeocdn.com/ts/136/375/136375440_1280.jpg', $provider->getReferenceImage($media));

        $this->assertEquals('0011/24', $provider->generatePath($media));
        $this->assertEquals('/updoads/media/0011/24/thumb_1023457_big.jpg', $provider->generatePublicUrl($media, 'big'));

    }

    public function testThumbnail()
    {

        $provider = $this->getProvider();

        $media = new Media;
        $media->setName('Blinky™');
        $media->setProviderName('vimeo');
        $media->setProviderReference('21216091');
        $media->setProviderMetadata(json_decode('{"type":"video","version":"1.0","provider_name":"Vimeo","provider_url":"http:\/\/vimeo.com\/","title":"Blinky\u2122","author_name":"Ruairi Robinson","author_url":"http:\/\/vimeo.com\/ruairirobinson","is_plus":"1","html":"<iframe src=\"http:\/\/player.vimeo.com\/video\/21216091\" width=\"1920\" height=\"1080\" frameborder=\"0\"><\/iframe>","width":"1920","height":"1080","duration":"771","description":"","thumbnail_url":"http:\/\/b.vimeocdn.com\/ts\/136\/375\/136375440_1280.jpg","thumbnail_width":1280,"thumbnail_height":720,"video_id":"21216091"}', true));

        $media->setId(1023457);

        $this->assertTrue($provider->requireThumbnails($media));

        $provider->addFormat('big', array('width' => 200, 'height' => 100, 'constraint' => true));

        $this->assertNotEmpty($provider->getFormats(), '::getFormats() return an array');

        $provider->generateThumbnails($media);

        $this->assertEquals('0011/24/thumb_1023457_big.jpg', $provider->generatePrivateUrl($media, 'big'));
    }

    public function testEvent()
    {
        $provider = $this->getProvider();

        $provider->addFormat('big', array('width' => 200, 'height' => 100, 'constraint' => true));

        $media = new Media;
        $media->setBinaryContent('BDYAbAtaDzA');
        $media->setId(1023456);

        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'Sonata\\MediaBundle\\Tests\\Provider\\FakeHttpWrapper');
        
        // pre persist the media
        $provider->prePersist($media);

        $this->assertEquals('Blinky™', $media->getName(), '::getName() return the file name');
        $this->assertEquals('BDYAbAtaDzA', $media->getProviderReference(), '::getProviderReference() is set');

        // post persit the media
        $provider->postPersist($media);

        $provider->postRemove($media);

        stream_wrapper_restore('http');
    }

}