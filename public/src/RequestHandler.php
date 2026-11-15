<?php
declare(strict_types=1);

/**
 * Reads the HTTP request, calls the right repository and answers with JSON.
 *   GET    api.php?resource=students            list
 *   GET    api.php?resource=students&id=3       one record
 *   POST   api.php?resource=students            create (JSON body)
 *   PUT    api.php?resource=students&id=3       update (JSON body)
 *   DELETE api.php?resource=students&id=3       delete
 *   GET    api.php?resource=transcript&id=3     transcript of student 3
 */
class RequestHandler
{
    public function handle(): void
    {
        try {
            [$status, $payload] = $this->dispatch();
            Response::json($status, $payload);
        } catch (ApiException $e) {
            Response::json($e->getStatus(), ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            // Never show raw SQL or PHP errors to the user: log them and send a generic message.
            error_log($e->getMessage());
            Response::json(500, ['error' => 'Something went wrong on the server. Please try again.']);
        }
    }

    /** @return array [HTTP status, payload] */
    private function dispatch(): array
    {
        $pdo = Database::connection();
        $resource = $_GET['resource'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        $id = isset($_GET['id']) ? Validator::id($_GET['id'], 'Id') : null;

        if ($resource === 'transcript') {
            if ($method !== 'GET' || $id === null) {
                throw new ApiException('Use GET with a student id.', 400);
            }
            return [200, ['data' => (new EnrollmentRepository($pdo))->transcript($id)]];
        }

        $repo = match ($resource) {
            'students'      => new StudentRepository($pdo),
            'courses'       => new CourseRepository($pdo),
            'prerequisites' => new PrerequisiteRepository($pdo),
            'enrollments'   => new EnrollmentRepository($pdo),
            default         => throw new ApiException('Unknown resource.', 404),
        };

        switch ($method) {
            case 'GET':
                return [200, ['data' => $id === null ? $repo->all() : $repo->find($id)]];
            case 'POST':
                return [201, ['data' => $repo->create($this->body()), 'message' => 'Record created.']];
            case 'PUT':
                return [200, ['data' => $repo->update($this->requireId($id), $this->body()), 'message' => 'Record updated.']];
            case 'DELETE':
                $repo->delete($this->requireId($id));
                return [200, ['message' => 'Record deleted.']];
            default:
                throw new ApiException('Method not allowed.', 405);
        }
    }

    private function requireId(?int $id): int
    {
        return $id ?? throw new ApiException('Id is required.', 400);
    }

    /** The JSON body of a POST or PUT request. */
    private function body(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            throw new ApiException('The request body must be valid JSON.', 400);
        }
        return $data;
    }
}
