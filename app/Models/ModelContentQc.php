<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelContentQc extends Model
{
    protected $table = 'content_qc';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['content_id', 'status', 'note', 'checker_id', 'checked_at'];
}