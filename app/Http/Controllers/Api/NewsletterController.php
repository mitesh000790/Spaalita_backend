<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\NewsletterEmailRequest;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterService;
use App\Support\Js;
use App\Support\Like;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NewsletterController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletter) {}

    /**
     * POST /api/newsletter/subscribe  {email, source?}
     */
    public function subscribe(NewsletterEmailRequest $request): JsonResponse
    {
        try {
            $source = in_array($request->input('source'), NewsletterSubscriber::SOURCES, true)
                ? $request->input('source')
                : 'footer';

            [$subscriber, $isNew] = $this->newsletter->subscribe($request->input('email'), $source);

            return response()->json([
                'success' => true,
                'message' => $isNew ? 'Thank you for subscribing to our newsletter' : 'This email is already subscribed',
                'data' => new NewsletterSubscriberResource($subscriber),
            ], $isNew ? 201 : 200);
        } catch (Throwable $exception) {
            return $this->serverError('Error subscribing to the newsletter', $exception);
        }
    }

    /**
     * POST /api/newsletter/unsubscribe  {email}
     */
    public function unsubscribe(NewsletterEmailRequest $request): JsonResponse
    {
        try {
            $subscriber = $this->newsletter->unsubscribe($request->input('email'));

            if ($subscriber === null) {
                return response()->json(['success' => false, 'message' => 'This email is not subscribed'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'You have been unsubscribed from our newsletter',
                'data' => new NewsletterSubscriberResource($subscriber),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error unsubscribing from the newsletter', $exception);
        }
    }

    /**
     * GET /api/newsletter?page=&limit=&search=&status=  (admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::lenient($request, defaultLimit: 20);
            $query = NewsletterSubscriber::query();

            if (Js::truthy($request->query('search'))) {
                $query->where('email', 'like', Like::contains((string) $request->query('search')));
            }

            if (Js::truthy($request->query('status'))) {
                $query->where('status', $request->query('status'));
            }

            $total = $query->count();
            $subscribers = $pagination->apply($query->newestFirst())->get();

            return response()->json([
                'success' => true,
                'data' => NewsletterSubscriberResource::collection($subscribers),
                'pagination' => [
                    'total' => $total,
                    'page' => $pagination->page,
                    'limit' => $pagination->limit,
                    'totalPages' => $pagination->pages($total),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching newsletter subscribers', $exception);
        }
    }

    /**
     * DELETE /api/newsletter/{id}  (admin)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $subscriber = NewsletterSubscriber::query()->find($id);

            if ($subscriber === null) {
                return response()->json(['success' => false, 'message' => 'Subscriber not found'], 404);
            }

            $subscriber->delete();

            return response()->json(['success' => true, 'message' => 'Subscriber deleted successfully']);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting subscriber', $exception);
        }
    }
}
