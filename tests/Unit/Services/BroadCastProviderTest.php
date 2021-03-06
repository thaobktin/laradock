<?php
namespace Tests\Unit\Services;

use App\DTO\ChannelEvent;
use App\Model\ChannelEventModel;
use App\Model\ChannelModel;
use App\Repository\ChannelRepository;
use App\Services\BroadcastingProvider;
use App\Services\ChannelInterface;
use App\Services\ContentEventProvider;
use App\Services\ContentProvider;
use App\Transformer\AstroModelTransformer;
use Illuminate\Database\Eloquent\Collection;

class BroadCastProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $repo;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $transformer;

    protected function setUp()
    {
        parent::setUp();

        $this->repo = $this->createMock(ChannelRepository::class);

        $this->transformer = $this->createMock(AstroModelTransformer::class);
    }

    public function testRegisterProvider()
    {
        $contentProvider = $this->createMock(ContentProvider::class);
        $contentProvider->expects(static::once())
            ->method('getServiceName')
            ->willReturn('ASTRO');

        $provider = new BroadcastingProvider($this->repo, $this->transformer);

        $expectedSameProvider = $provider->registerProvider($contentProvider);

        static::assertSame($expectedSameProvider, $provider, 'Must be the same');
    }

    public function test_get_channel_through_client()
    {
        $providerService = 'ASTRO';

        $channels   = [
            $this->createPartialMock(ChannelInterface::class, ['getName', 'getNumber', 'getChannelId']),
            $this->createPartialMock(ChannelInterface::class, ['getName', 'getNumber', 'getChannelId']),
        ];
        $collection = $this->createMock(Collection::class);
        $collection->method('count')->willReturn(0);

        $this->repo->expects(static::once())->method('getChannels')
            ->with($providerService)
            ->willReturn($collection);
        $this->repo->expects(static::exactly(2))->method('saveChannel');

        $contentProvider = $this->createMock(ContentProvider::class);
        $contentProvider->expects(static::once())
            ->method('getServiceName')
            ->willReturn($providerService);

        $contentProvider->expects(static::once())
            ->method('getChannels')
            ->willReturn($channels);

        $provider = new BroadcastingProvider($this->repo, $this->transformer);
        $provider->registerProvider($contentProvider);

        $results = $provider->getChannelsFrom($providerService);

        static::assertCount(2, $results, 'Must respect input');
    }

    public function test_get_channel_through_source()
    {
        $providerService = 'ASTRO';

        $channels   = [
            $this->createPartialMock(ChannelModel::class, ['getName', 'getNumber']),
            $this->createPartialMock(ChannelModel::class, ['getName', 'getNumber']),
        ];
        $collection = $this->getMockBuilder(Collection::class)->getMock();
        $collection->method('count')->willReturn(2);
        $collection->method('getIterator')->willReturn($channels);

        $this->repo->expects(static::once())->method('getChannels')
            ->with($providerService)
            ->willReturn($collection);

        $contentProvider = $this->createMock(ContentProvider::class);
        $contentProvider->expects(static::once())
            ->method('getServiceName')
            ->willReturn($providerService);

        $this->transformer->expects(static::exactly(2))->method('transform')
            ->willReturnOnConsecutiveCalls(
                [$channels[0]],
                [$channels[1]]
            );
        $provider = new BroadcastingProvider($this->repo, $this->transformer);
        $provider->registerProvider($contentProvider);

        $results = $provider->getChannelsFrom($providerService);

        static::assertCount(2, $results, 'Must respect input');
    }

    public function test_get_events_through_client()
    {
        $providerService = 'ASTRO';
        $channelId       = 12;

        $events     = [
            $this->createMock(ChannelEvent::class),
            $this->createMock(ChannelEvent::class),
        ];
        $collection = $this->createMock(Collection::class);
        $collection->method('count')->willReturn(0);

        $this->repo->expects(static::once())->method('getEvents')
            ->with($channelId, $providerService)
            ->willReturn($collection);
        $this->repo->expects(static::exactly(2))->method('saveEvent');

        $contentProvider = $this->createMock([ContentProvider::class, ContentEventProvider::class]);
        $contentProvider->expects(static::once())
            ->method('getServiceName')
            ->willReturn($providerService);

        $contentProvider->expects(static::once())
            ->method('getChannelEvents')
            ->with($channelId)
            ->willReturn($events);

        $provider = new BroadcastingProvider($this->repo, $this->transformer);
        $provider->registerProvider($contentProvider);

        $results = $provider->getChannelEvents($channelId, $providerService);

        static::assertCount(2, $results, 'Must respect input');
    }


    public function test_get_channel_events_through_source()
    {
        $providerService = 'ASTRO';
        $channelId       = 12341;

        $event1 = $this->createPartialMock(ChannelEventModel::class, ['getAttributes']);
        $event1
            ->method('getAttributes')->willReturn(
                ['provider' => $providerService]
            );
        $event2 = $this->createPartialMock(ChannelEventModel::class, ['getAttributes']);
        $event2
            ->method('getAttributes')->willReturn(
                ['provider' => $providerService]
            );
        $channelEvents = [$event1, $event2];
        $collection    = $this->getMockBuilder(Collection::class)->getMock();
        $collection->method('count')->willReturn(2);
        $collection->method('getIterator')->willReturn($channelEvents);

        $this->repo->expects(static::once())->method('getEvents')
            ->with($channelId, $providerService)
            ->willReturn($collection);

        $contentProvider = $this->createMock([ContentProvider::class, ContentEventProvider::class]);
        $contentProvider->expects(static::never())
            ->method('getChannelEvents');

        $provider = new BroadcastingProvider($this->repo, $this->transformer);
        $provider->registerProvider($contentProvider);

        $results = $provider->getChannelEvents($channelId, $providerService);

        static::assertCount(2, $results, 'Must respect input');
    }
}
