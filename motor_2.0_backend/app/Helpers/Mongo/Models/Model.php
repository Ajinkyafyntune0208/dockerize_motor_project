<?php

namespace App\Helpers\Mongo\Models;
use App\Helpers\Mongo\MongoDBConnection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class Model
{
    // The MongoDB collection instance
    // This will be initialized in the constructor
    public $collection;

    // The name of the collection in MongoDB
    // This should be set in the child class
    protected $collectionName;

    // Filter to apply to the collection
    // This is used to build queries dynamically
    protected $filter = [];

    // Projection to specify which fields to return
    // This is useful for reducing the amount of data returned
    protected $projection = [];

    // Indicates if the model should use timestamps
    // This will automatically manage created_at and updated_at fields
    protected $timestamps = true;

    // Sort order for the results
    protected $sort = [];

    // Pagination settings
    // These can be used to limit the number of results returned
    protected $limit;
    protected $skip;

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $displayTimezone = 'Asia/Kolkata';

    protected $casts = [];


    public function __construct()
    {
        // Initialize the MongoDB connection
        $connection = (new MongoDBConnection())->connect();

        // Set the collection based on the collection name
        $this->collection = $connection->selectCollection($this->collectionName);
    }

    protected function now()
    {
        return new UTCDateTime((new \DateTime())->getTimestamp() * 1000);
        // return date('Y-m-d H:i:s');
    }


    // Find a document by its ID
    // This method will return a single document based on its ID
    public function find($id)
    {
        if ($id instanceof ObjectId) {
            $objectId = $id;
        } elseif (is_string($id)) {
            try {
                $objectId = new ObjectId($id);
            } catch (\Exception $e) {
                return null;
            }
        } else {
            return null;
        }


        $result = $this->collection->findOne(
            ['_id' => $objectId],
            ['projection' => $this->projection]
        );
        

        $result = $result ? (array) $result : null;

        if (!empty($result)) {
            $result = $this->formatData($result);
        }

        return $result;
    }

    public function first()
    {
        $result = $this->collection->findOne(
            $this->filter,
            ['projection' => $this->projection]
        );

        $this->resetQueryOptions();

        $result = $result ? (array) $result : null;

        if (!empty($result)) {
            $result = $this->formatData($result);
        }
        return $result;
    }

    public function get()
    {
        $options = ['projection' => $this->projection];

        if (!empty($this->sort)) {
            $options['sort'] = $this->sort;
        }
        if ($this->limit) {
            $options['limit'] = $this->limit;
        }
        if ($this->skip) {
            $options['skip'] = $this->skip;
        }
        $cursor = $this->collection->find($this->filter, $options);

        $this->resetQueryOptions();

        return collect(iterator_to_array($cursor))->map(function ($item) {
            return $this->formatData((array) $item);
        });
    }

    public function orderBy($field, $direction = 'asc')
    {
        $this->sort = [$field => strtolower($direction) === 'desc' ? -1 : 1];
        return $this;
    }

    public function select(array $fields = [])
    {
        $this->projection = array_fill_keys($fields, 1);
        return $this;
    }

    // Set the limit for the number of results
    // This method will limit the number of documents returned
    public function limit($value)
    {
        $this->limit = (int) $value;
        return $this;
    }

    // Set the number of documents to skip
    // This is useful for pagination
    public function skip($value)
    {
        $this->skip = (int) $value;
        return $this;
    }

    public function paginate($perPage = 10, $page = 1, $baseUrl = null)
    {
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);

        $total = $this->collection->countDocuments($this->filter);

        $this->limit($perPage)->skip(($page - 1) * $perPage);
        $data = $this->get();

        $lastPage = (int) ceil($total / $perPage);
        $from = ($total === 0) ? 0 : (($page - 1) * $perPage) + 1;
        $to = ($from - 1) + count($data);

        if (!$baseUrl) {
            $baseUrl = url()->current(); // Laravel helper
        }

        $queryString = request()->except('page');
        $query = http_build_query($queryString);

        $appendQuery = fn($pageNum) => $query
            ? "{$baseUrl}?{$query}&page={$pageNum}"
            : "{$baseUrl}?page={$pageNum}";

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
            'next_page_url' => $page < $lastPage ? $appendQuery($page + 1) : null,
            'prev_page_url' => $page > 1 ? $appendQuery($page - 1) : null,
            'path' => $baseUrl,
        ];
    }


    public function where(array $condition)
    {
        foreach ($condition as $key => $value) {
            if ($key === '_id' && is_string($value)) {
                try {
                    $value = new ObjectId($value);
                } catch (\Exception $e) {
                    // keep as it is
                }
            }

            if (!isset($this->filter[$key])) {
                $this->filter[$key] = $value;
            } else {
                // Handle if key already exists (e.g., with `$in`, `$gt`, etc.)
                $this->filter[$key] = array_merge(
                    (array) $this->filter[$key],
                    (array) $value
                );
            }
        }

        return $this;
    }

    //create a new document
    // This method will insert a new document into the collection
    public function create($data)
    {
        // If timestamps are enabled, set created_at and updated_at fields
        if ($this->timestamps) {
            $data['created_at'] = $this->now();
            $data['updated_at'] = $this->now();
        }

        if (!empty($this->casts)) {
            foreach ($this->casts as $field => $cast) {
                if (isset($data[$field]) && class_exists($cast)) {
                    $data[$field] = (new $cast)->set($data[$field]);
                }
            }
        }

        $result = $this->collection->insertOne($data);

        if ($result->isAcknowledged()) {
            $data['_id'] = $result->getInsertedId();
            return $this->formatData($data);
        }
        
        return false;
    }

    public function insert(array $documents)
    {
        return $this->collection->insertMany($documents);
    }
    

    // Update or create a document based on the filter
    // This method will update an existing document if it matches the filter,
    public function updateOrCreate($filter, $data)
    {
        // If timestamps are enabled, set created_at and updated_at fields
        if ($this->timestamps) {
            $existing = $this->collection->findOne($filter);
            $data['updated_at'] = $this->now();
            if (!$existing) {
                $data['created_at'] = $this->now();
                $data['updated_at'] = $this->now();
            }
        }

        if (!empty($this->casts)) {
            foreach ($this->casts as $field => $cast) {
                if (isset($data[$field])) {
                    $data[$field] = (new $cast)->set(null, $field, $data[$field], []);
                }
            }
        }
        

        $options = ['upsert' => true];
        return $this->collection->updateOne(
            $filter,
            ['$set' => $data],
            $options
        );
    }

    public function update(array $data)
    {
        if ($this->timestamps) {
            $data['updated_at'] = $this->now();
        }

        if (!empty($this->casts)) {
            foreach ($this->casts as $field => $cast) {
                if (isset($data[$field])) {
                    $data[$field] = (new $cast)->set(null, $field, $data[$field], []);
                }
            }
        }

        return $this->collection->updateMany($this->filter, ['$set' => $data]);
    }

    public function delete(array $filter)
    {
        return $this->collection->deleteMany($filter);
    }

    protected function formatData(array $document): array
    {
        $formattingfields = ['created_at', 'updated_at', '_id'];
        $formattingfields = array_merge($formattingfields, array_keys($this->casts));
        foreach ($formattingfields as $field) {
            if (isset($document[$field]) && $document[$field] instanceof UTCDateTime) {
                $date = $document[$field]->toDateTime();
                $date->setTimezone(new \DateTimeZone($this->displayTimezone)); // convert to IST
                $document[$field] = $date->format($this->dateFormat);
            } elseif (isset($document[$field]) && $document[$field] instanceof ObjectId) {
                $document[$field] = (string) $document[$field];
            } elseif (in_array($field, array_keys($this->casts)) && isset($document[$field])) {
                $document[$field] = (new $this->casts[$field])->get($document[$field]);
            }
        }
        return $document;
    }

    protected function resetQueryOptions()
    {
        $this->filter = [];
        $this->projection = [];
        $this->limit = null;
        $this->skip = null;
        $this->sort = [];
    }
}
