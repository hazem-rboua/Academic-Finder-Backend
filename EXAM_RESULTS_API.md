# Exam Results Processing API

## Overview

This API endpoint processes exam results from the external `twindix_api` database and calculates job compatibility scores based on the Academic Finder algorithm.

## Endpoint

```
POST /api/exam-results/process
```

## Request

### Headers
```
Content-Type: application/json
Accept: application/json
Accept-Language: en|ar (optional, defaults to en)
```

### Body
```json
{
  "exam_code": "EXAM123456"
}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| exam_code | string | Yes | The unique exam code to process |

## Response

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Exam results processed successfully",
  "data": {
    "exam_code": "EXAM123456",
    "total_questions": 45,
    "reference_values": [
      {
        "reference": "R17",
        "total_value": 3,
        "titles": {
          "التفكير الإبداعي": {
            "title": "التفكير الإبداعي",
            "value": 1,
            "ones_count": 2,
            "total_questions": 3
          },
          "اللمسة الفنية": {
            "title": "اللمسة الفنية",
            "value": 1,
            "ones_count": 3,
            "total_questions": 3
          }
        }
      }
    ],
    "job_compatibility": [
      {
        "reference": "R17",
        "total_value": 3,
        "titles": {...},
        "job_descriptions": [...]
      }
    ]
  }
}
```

### Error Responses

#### 404 Not Found
```json
{
  "success": false,
  "message": "Exam not found"
}
```

#### 422 Validation Error
```json
{
  "success": false,
  "message": "The exam code field is required.",
  "errors": {
    "exam_code": ["The exam code field is required."]
  }
}
```

#### 500 Server Error
```json
{
  "success": false,
  "message": "An error occurred while processing exam results"
}
```

## Algorithm Explanation

The Academic Finder algorithm works as follows:

1. **Read Exam Answers**: Retrieves the exam enrollment record from the `twindix_api.exam_enrollments` table based on the provided exam code.

2. **Parse Answers**: The answers are stored as JSON in the format:
   ```json
   {
     "14203": 0,
     "416782": 0,
     "1281": 1,
     "1943": 1
   }
   ```
   Where the key is the question number and the value is the answer (0 or 1).

3. **Map Questions to Titles**: Uses the CSV file (`public/AcademicFinderAlgorithm.csv`) to map question numbers to their titles and references.

4. **Calculate Title Values**: Groups answers by title. For each title:
   - Count how many answers have value `1`
   - If **2 or more** answers are `1`, the final value for that title is `1`
   - Otherwise, the final value is `0`

5. **Group by Reference**: Groups titles by their reference (e.g., R17, R18, etc.) and calculates the total value for each reference.

6. **Match with Job Titles**: Retrieves job descriptions from the `twindix_api.template` table (ID = 6) and matches them with the calculated reference values.

7. **Sort Results**: Sorts job compatibility results by total_value in descending order to show the best matches first.

## Database Configuration

### Environment Variables

Add the following to your `.env` file:

```env
# External Database Connection (twindix_api)
EXTERNAL_DB_HOST=127.0.0.1
EXTERNAL_DB_PORT=3306
EXTERNAL_DB_DATABASE=twindix_api
EXTERNAL_DB_USERNAME=your_username
EXTERNAL_DB_PASSWORD=your_password
```

### Database Tables Used

1. **twindix_api.exam_enrollments**
   - `exam_code`: Unique exam identifier
   - `answers`: JSON field containing question-answer pairs

2. **twindix_api.template**
   - `id`: Template ID (uses ID = 6)
   - `references`: JSON field containing job title descriptions

## CSV File Structure

The `public/AcademicFinderAlgorithm.csv` file has the following structure:

```csv
code,Title,Reference,,
,وظائف التفكير المفتوح,R17-Title,,
1111,التفكير الإبداعي,R17,,
1112,التفكير الإبداعي,R17,,
1113,التفكير الإبداعي,R17,,
```

- **Column 1**: Question number (code)
- **Column 2**: Title (Arabic description)
- **Column 3**: Reference code (e.g., R17, R18)

## Testing

### Using cURL

```bash
curl -X POST http://localhost:8000/api/exam-results/process \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"exam_code": "EXAM123456"}'
```

### Using Postman

1. Create a new POST request
2. URL: `http://localhost:8000/api/exam-results/process`
3. Headers:
   - `Content-Type: application/json`
   - `Accept: application/json`
4. Body (raw JSON):
   ```json
   {
     "exam_code": "EXAM123456"
   }
   ```

## Example Use Case

A student completes an exam on the external system. The frontend receives the exam code and sends it to this API endpoint. The API:

1. Retrieves the student's answers from the external database
2. Processes them through the Academic Finder algorithm
3. Returns job compatibility scores
4. The frontend displays the top matching job categories to help guide the student's career path

## Error Handling

The service includes comprehensive error handling:

- **Exam Not Found**: Returns 404 if the exam code doesn't exist
- **Invalid Data**: Returns 500 if the answers JSON is malformed
- **CSV File Issues**: Returns 500 if the CSV file is missing or unreadable
- **Database Connection**: Returns 500 if unable to connect to external database

All errors are logged to `storage/logs/laravel.log` for debugging.

## Deployment Notes

1. Ensure the external database credentials are correctly configured in `.env`
2. Verify the CSV file exists at `public/AcademicFinderAlgorithm.csv`
3. Test the database connection before deploying
4. Monitor logs for any processing errors

## Future Enhancements

Potential improvements for future versions:

- Cache CSV mapping for better performance
- Add authentication/authorization if needed
- Support batch processing of multiple exam codes
- Add more detailed analytics and reporting
- Support for different exam types or versions

