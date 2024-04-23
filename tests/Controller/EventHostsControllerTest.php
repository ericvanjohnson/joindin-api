<?php

namespace Joindin\Api\Test\Controller;

use Exception;
use Joindin\Api\Controller\EventHostsController;
use Joindin\Api\Model\EventHostMapper;
use Joindin\Api\Model\EventMapper;
use Joindin\Api\Model\UserMapper;
use Joindin\Api\Request;
use Joindin\Api\View\ApiView;
use PDO;
use PHPUnit\Framework\TestCase;
use Teapot\StatusCode\Http;

final class EventHostsControllerTest extends TestCase
{
    /**
     * @group uses_pdo
     */
    public function testThatNotLoggedInUsersCanNotAddAHost(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You must be logged in to create data');
        $this->expectExceptionCode(Http::UNAUTHORIZED);

        $controller = new EventHostsController();

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $db      = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $controller->addHost($request, $db);
    }

    public function testThatRemovingHostWithoutLoginFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You must be logged in to remove data');
        $this->expectExceptionCode(Http::UNAUTHORIZED);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = null;

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $constructor = new EventHostsController();
        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatMissingEventThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Event not found');
        $this->expectExceptionCode(Http::NOT_FOUND);

        $controller = new EventHostsController();

        $em = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $em->method('getEventById')->willThrowException(new \Exception('Event not found', Http::NOT_FOUND));

        $controller->setEventMapper($em);

        $request               = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->url_elements = [3 => 1];
        $request->user_id      = 2;

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $controller->addHost($request, $db);
    }

    public function testThatRemovingOneselfThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You are not allowed to remove yourself from the host-list');
        $this->expectExceptionCode(Http::FORBIDDEN);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 2;
        $request->url_elements = [3 => 1, 5 => 2];

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $constructor = new EventHostsController();
        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatInvalidEventThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Event not found');
        $this->expectExceptionCode(Http::NOT_FOUND);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 1;
        $request->url_elements = [
            3 => 4,
            5 => 2,
        ];

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $eventMapper = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $eventMapper->method('getEventById')->willThrowException(new Exception('Event not found', Http::NOT_FOUND));

        $constructor = new EventHostsController();
        $constructor->setEventMapper($eventMapper);

        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatExceptionIsThrownWhenNonAdminUserTriesToAddHostToEvent(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You do not have permission to add hosts to this event');
        $this->expectExceptionCode(Http::FORBIDDEN);

        $controller = new EventHostsController();

        $em = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $em->method('getEventById')->willReturn(['event']);
        $em->method('thisUserHasAdminOn')->willReturn(false);

        $controller->setEventMapper($em);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->url_elements = [3 => 1];
        $request->user_id = 2;

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $controller->addHost($request, $db);
    }

    public function testThatUserThatIsNotAdminOnEventWillThrowException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You do not have permission to remove hosts from this event');
        $this->expectExceptionCode(Http::FORBIDDEN);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 1;
        $request->url_elements = [
            3 => 4,
            5 => 2,
        ];

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $eventMapper = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $eventMapper->method('getEventById')->willReturn(['event']);
        $eventMapper->method('thisUserHasAdminOn')->willReturn(false);

        $constructor = new EventHostsController();
        $constructor->setEventMapper($eventMapper);

        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatExceptionIsThrownWhenUnknownUserShallBeAddedAsHostToEvent(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No User found');
        $this->expectExceptionCode(Http::NOT_FOUND);

        $controller = new EventHostsController();

        $em = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $em->method('getEventById')->willReturn(['event']);
        $em->method('thisUserHasAdminOn')->willReturn(true);

        $controller->setEventMapper($em);

        $um = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $um->method('getUserIdFromUsername')->with($this->equalTo('myhostname'))->willReturn(false);

        $controller->setUserMapper($um);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->url_elements = [3 => 1];
        $request->user_id = 2;
        $request->method('getStringParameter')->willReturn('myhostname');

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $controller->addHost($request, $db);
    }

    public function testThatSettingUnknownUserWillThrowException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No User found');
        $this->expectExceptionCode(Http::NOT_FOUND);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 1;
        $request->url_elements = [
            3 => 4,
            5 => 2,
        ];

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $eventMapper = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $eventMapper->method('getEventById')->willReturn(['event']);
        $eventMapper->method('thisUserHasAdminOn')->willReturn(true);

        $userMapper = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $userMapper->method('getUserById')->willReturn(false);

        $constructor = new EventHostsController();
        $constructor->setEventMapper($eventMapper);
        $constructor->setUserMapper($userMapper);

        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatExceptionIsThrownWhenEventHostMapperHasProblems(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Something went wrong');
        $this->expectExceptionCode(Http::BAD_REQUEST);

        $controller = new EventHostsController();

        $em = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $em->method('getEventById')->willReturn(['event']);
        $em->method('thisUserHasAdminOn')->willReturn(true);

        $controller->setEventMapper($em);

        $um = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $um->method('getUserIdFromUsername')->with($this->equalTo('myhostname'))->willReturn(13);

        $controller->setUserMapper($um);

        $ehm = $this->getMockBuilder(EventHostMapper::class)->disableOriginalConstructor()->getMock();
        $ehm->expects($this->once())->method('addHostToEvent')->with($this->equalTo(12), $this->equalTo(13))->willReturn(false);

        $controller->setEventHostMapper($ehm);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->url_elements = [3 => 12];
        $request->user_id = 2;
        $request->method('getStringParameter')->willReturn('myhostname');

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $controller->addHost($request, $db);
    }

    public function testThatViewGetsCorrectValuesWhenEverythingWorksAsExpected(): void
    {
        $controller = new EventHostsController();

        $em = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $em->method('getEventById')->willReturn(['event']);
        $em->method('thisUserHasAdminOn')->willReturn(true);

        $controller->setEventMapper($em);

        $um = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $um->method('getUserIdFromUsername')->with($this->equalTo('myhostname'))->willReturn(13);

        $controller->setUserMapper($um);

        $ehm = $this->getMockBuilder(EventHostMapper::class)->disableOriginalConstructor()->getMock();
        $ehm->expects($this->once())->method('addHostToEvent')->with($this->equalTo(12), $this->equalTo(13))->willReturn(true);

        $controller->setEventHostMapper($ehm);

        $view = $this->getMockBuilder(ApiView::class)->disableOriginalConstructor()->getMock();
        $view->expects($this->once())->method('setHeader')->with(
            $this->equalTo('Location'),
            $this->equalTo('foo/v2.1/events/12/hosts')
        );
        $view->expects($this->once())->method('setResponseCode')->with($this->equalTo(Http::CREATED));
        $view->expects($this->once())->method('setNoRender')->with($this->equalTo(true));

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->url_elements = [3 => 12];
        $request->user_id = 2;
        $request->base = 'foo';
        $request->method('getStringParameter')->willReturn('myhostname');
        $request->method('getView')->willReturn($view);

        $controller->addHost($request, $db);
    }

    public function testThatFailureWhileRemovingUserAsHostWillThrowException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Something went wrong');
        $this->expectExceptionCode(Http::BAD_REQUEST);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 1;
        $request->url_elements = [
            3 => 4,
            5 => 2,
        ];

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $eventMapper = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $eventMapper->method('getEventById')->willReturn(['event']);
        $eventMapper->method('thisUserHasAdminOn')->willReturn(true);

        $userMapper = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $userMapper->method('getUserById')->willReturn(['ID' => 123]);

        $eventHostMapper = $this->getMockBuilder(EventHostMapper::class)->disableOriginalConstructor()->getMock();
        $eventHostMapper->method('removeHostFromEvent')->willReturn(false);

        $constructor = new EventHostsController();
        $constructor->setEventMapper($eventMapper);
        $constructor->setUserMapper($userMapper);
        $constructor->setEventHostMapper($eventHostMapper);

        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatRemovingUserAsHostSetsCorrectValues(): void
    {
        $view = $this->getMockBuilder(ApiView::class)->getMock();
        $view->expects($this->once())->method('setHeader')->with('Location', 'base/version/events/4/hosts');
        $view->expects($this->once())->method('setResponseCode')->with(Http::NO_CONTENT);
        $view->expects($this->once())->method('setNoRender')->with(true);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 1;
        $request->url_elements = [
            3 => 4,
            5 => 2,
        ];
        $request->base = 'base';
        $request->version = 'version';
        $request->method('getView')->willReturn($view);

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $eventMapper = $this->getMockBuilder(EventMapper::class)->disableOriginalConstructor()->getMock();
        $eventMapper->expects($this->once())->method('getEventById')->willReturn(['event']);
        $eventMapper->expects($this->once())->method('thisUserHasAdminOn')->willReturn(true);

        $userMapper = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $userMapper->expects($this->once())->method('getUserById')->willReturn(['ID' => 123]);

        $eventHostMapper = $this->getMockBuilder(EventHostMapper::class)->disableOriginalConstructor()->getMock();
        $eventHostMapper->expects($this->once())->method('removeHostFromEvent')->willReturn(true);

        $constructor = new \Joindin\Api\Controller\EventHostsController();
        $constructor->setEventMapper($eventMapper);
        $constructor->setUserMapper($userMapper);
        $constructor->setEventHostMapper($eventHostMapper);

        $constructor->removeHostFromEvent($request, $db);
    }

    public function testThatGettingEventHostWapperMithoutSettingFirstWorksAsExpected(): void
    {
        $controller = new EventHostsController();

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();

        $automatedEventHostMapper = $controller->getEventHostMapper($request, $db);

        $this->assertInstanceOf(EventHostMapper::class, $automatedEventHostMapper);
        $this->assertSame($automatedEventHostMapper, $controller->getEventHostMapper($request, $db));
    }

    public function testThatGettingUserMapperWithoutSettingFirstWorksAsExpected(): void
    {
        $controller = new EventHostsController();

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();

        $automatedUserMapper = $controller->getUserMapper($request, $db);

        $this->assertInstanceOf(UserMapper::class, $automatedUserMapper);
        $this->assertSame($automatedUserMapper, $controller->getUserMapper($request, $db));
    }

    public function testThatGettingEventMapperWithoutSettingFirstWorksAsExpected(): void
    {
        $controller = new EventHostsController();

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();

        $automatedEventMapper = $controller->getEventMapper($request, $db);

        $this->assertInstanceOf(EventMapper::class, $automatedEventMapper);
        $this->assertSame($automatedEventMapper, $controller->getEventMapper($request, $db));
    }
}
