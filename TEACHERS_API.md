# Teachers API Documentation

## Overview
A dedicated API endpoint for managing and retrieving teachers (employees with subject assignments) has been added to the HR module.

## Base Endpoint
`GET /api/v1/hr/teachers`

## Available Endpoints

### 1. Get All Teachers
`GET /api/v1/hr/teachers`

**Query Parameters:**
- `search` - Search by name, employee UID, or mobile
- `status` - Filter by status (default: `active`, use `all` for all statuses)
- `designation_id` - Filter by designation ID
- `hr_section_id` - Filter by HR section/department ID
- `employment_type` - Filter by employment type (permanent/contract/temporary)
- `subject_id` - Filter by subject assignment
- `class_config_id` - Filter by class assignment
- `sort` - Sort field (name, employee_uid, joining_date, created_at)
- `per_page` - Results per page (default: 25, max: 200)

**Response:**
```json
{
  "success": true,
  "message": "Teachers retrieved successfully.",
  "data": [
    {
      "id": 14,
      "employee_uid": "EMP-2026-01003-0014",
      "name": "Kirsten Marks",
      "designation": { "id": 3, "name": "Professor" },
      "hr_section": { "id": 5, "name": "Science Department" },
      "subject_teachers": [...]
    }
  ],
  "meta": {
    "pagination": {
      "total": 14,
      "per_page": 25,
      "current_page": 1,
      "last_page": 1
    }
  }
}
```

### 2. Get Available Teachers
`GET /api/v1/hr/teachers/available`

Returns only active teachers without leaving dates. Useful for dropdowns and selects.

**Query Parameters:**
- `subject_id` - Filter by subject assignment
- `class_config_id` - Filter by class assignment

### 3. Get Teachers by Subject
`GET /api/v1/hr/teachers/subject/{subjectId}`

Returns all teachers assigned to a specific subject.

### 4. Get Teachers by Class
`GET /api/v1/hr/teachers/class/{classConfigId}`

Returns all teachers assigned to a specific class configuration.

### 5. Get Single Teacher
`GET /api/v1/hr/teachers/{id}`

Returns detailed information about a specific teacher including all their subject assignments.

### 6. Get Teacher Classes
`GET /api/v1/hr/teachers/{id}/classes`

Returns all class assignments for a specific teacher.

## Authentication & Authorization

All endpoints require:
- **Authentication**: Sanctum SPA cookie session
- **Authorization**: `hr.manage` permission
- **Branch Context**: Active branch context set via middleware

## How It Works

The teacher identification is based on the `teachers()` scope in the Employee model:
```php
public function scopeTeachers($query)
{
    return $query->whereHas('subjectTeachers');
}
```

This means any employee who has at least one active subject assignment is considered a teacher.

## Usage Examples

```javascript
// Get all active teachers
const response = await api.get('/hr/teachers');

// Get teachers for a specific subject
const mathTeachers = await api.get('/hr/teachers/subject/5');

// Get available teachers for dropdown
const availableTeachers = await api.get('/hr/teachers/available');

// Search teachers
const searchResults = await api.get('/hr/teachers?search=john');

// Get teacher's class assignments
const teacherClasses = await api.get('/hr/teachers/14/classes');
```

## Testing

The API has been tested with the following scenarios:
- Returns only teachers (employees with subject assignments)
- Filters by status, designation, department, and employment type
- Provides available teachers (active, no leaving date)
- Filters by subject and class assignments
- Returns proper pagination and sorting

## Postman Collection

The teacher endpoints have been added to the existing Postman collection (`decentedu.postman_collection.json`) under the **HR** section:

- **Teachers List** - Get all teachers with filtering and pagination
- **Teachers Available** - Get available teachers for dropdowns
- **Teachers by Subject** - Get teachers assigned to a specific subject
- **Teachers by Class** - Get teachers assigned to a specific class
- **Show Teacher** - Get single teacher details
- **Teacher Classes** - Get teacher's class assignments

All endpoints are ready to use in Postman with the existing authentication and branch context setup.