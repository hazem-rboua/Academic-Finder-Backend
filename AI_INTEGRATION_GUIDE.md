# AI Job Recommendation Integration Guide

## Overview
The exam results processing system now integrates with an external AI API to provide intelligent job recommendations based on the calculated exam results. The integration is designed to be resilient and will continue to work even if the AI service is unavailable.

## Features
- ✅ Automatic AI recommendations after exam processing
- ✅ Multi-language support (English and Arabic)
- ✅ Retry logic with exponential backoff
- ✅ Graceful degradation (works without AI if unavailable)
- ✅ Comprehensive logging for monitoring
- ✅ Configurable timeout and retry settings

## Configuration

### Environment Variables
Add the following to your `.env` file:

```env
# AI API Configuration
AI_API_BASE_URL=https://acdmic-ai.twindix.com
AI_API_TIMEOUT=10
AI_API_RETRY_TIMES=3
AI_API_ENABLED=true
```

### Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `AI_API_BASE_URL` | `https://acdmic-ai.twindix.com` | Base URL of the AI API |
| `AI_API_TIMEOUT` | `10` | Request timeout in seconds |
| `AI_API_RETRY_TIMES` | `3` | Number of retry attempts on failure |
| `AI_API_ENABLED` | `true` | Enable/disable AI integration |

## How It Works

### Flow Diagram
```
1. Client requests exam processing
2. System processes exam answers
3. Calculates selected_branches and environment_status
4. Calls AI API with exam results
   - English: POST /job-bar/recommend
   - Arabic: POST /job-bar/recommend/ar
5. AI API returns recommendations (or times out)
6. System merges AI data with exam results
7. Returns complete response to client
```

### Request to AI API
**Endpoint**: 
- English: `POST https://acdmic-ai.twindix.com/job-bar/recommend`
- Arabic: `POST https://acdmic-ai.twindix.com/job-bar/recommend/ar`

**Headers**:
```
Content-Type: application/json
Accept: application/json
```

**Body**:
```json
{
  "job_title": "Airport Operations Director",
  "industry": "Aviation",
  "seniority": "Senior Management",
  "selected_branches": [
    {
      "job_type": "Open Thinking Jobs",
      "chosen_competencies": [1, 0, 0, 0, 0]
    },
    // ... 15 more job types
  ],
  "environment_status": [
    {
      "question": 1,
      "selected_option": 0
    },
    // ... 9 more environment questions
  ]
}
```

### Response Format
```json
{
  "success": true,
  "message": "Exam processed successfully",
  "data": {
    "job_title": "Airport Operations Director",
    "industry": "Aviation",
    "seniority": "Senior Management",
    "selected_branches": [...],
    "environment_status": [...],
    "ai_recommendations": {
      // AI API response (structure depends on AI service)
      // null if AI service unavailable
    },
    "ai_available": true  // false if AI service unavailable
  }
}
```

## Error Handling

### Retry Logic
The system implements exponential backoff retry strategy:
- **Attempt 1**: Immediate
- **Attempt 2**: Wait 1 second
- **Attempt 3**: Wait 2 seconds
- **Attempt 4**: Wait 4 seconds

### Failure Scenarios

#### 1. AI API Unavailable
```json
{
  "ai_recommendations": null,
  "ai_available": false
}
```
**Behavior**: System continues normally, returns exam results without AI data.

#### 2. AI API Timeout
**Timeout**: 10 seconds per request
**Behavior**: After 3 retries and timeouts, returns exam results without AI data.

#### 3. AI API Error Response
**Status Codes**: 4xx or 5xx
**Behavior**: Logs error, returns exam results without AI data.

#### 4. Network Error
**Behavior**: Retries 3 times with exponential backoff, then returns exam results without AI data.

## Logging

### Success Logs
```
[INFO] Calling AI API for job recommendations
[INFO] AI API request successful (duration: 234ms, status: 200)
[INFO] AI recommendations received successfully
```

### Warning Logs
```
[WARNING] AI API request failed, retrying...
[WARNING] AI recommendations not available, continuing without AI data
```

### Error Logs
```
[ERROR] AI API returned error response (status: 500, duration: 456ms)
[ERROR] AI API request failed after retries (error: Connection timeout)
```

## Testing

### Manual Testing

#### 1. Test with AI API Available
```bash
curl -X POST http://your-api-url/api/exam-results/process \
  -H "Content-Type: application/json" \
  -H "Accept-Language: en" \
  -d '{"exam_code":"YOUR_EXAM_CODE"}'
```

**Expected**: Response includes `ai_recommendations` object and `ai_available: true`

#### 2. Test with AI API Disabled
Set in `.env`:
```env
AI_API_ENABLED=false
```

**Expected**: Response includes `ai_recommendations: null` and `ai_available: false`

#### 3. Test with Arabic Locale
```bash
curl -X POST http://your-api-url/api/exam-results/process \
  -H "Content-Type: application/json" \
  -H "Accept-Language: ar" \
  -d '{"exam_code":"YOUR_EXAM_CODE"}'
```

**Expected**: AI API called at `/job-bar/recommend/ar` endpoint

#### 4. Test AI API Timeout
Set in `.env`:
```env
AI_API_TIMEOUT=1
```

**Expected**: Quick timeout, retries, then returns without AI data

### Monitoring

Check logs for AI API performance:
```bash
# View AI API calls
tail -f storage/logs/laravel.log | grep "AI API"

# Check for failures
grep "AI API request failed" storage/logs/laravel.log

# Monitor response times
grep "AI API request successful" storage/logs/laravel.log
```

## Performance Considerations

### Response Time Impact
- **AI Available**: +200-500ms average
- **AI Timeout (3 retries)**: +30-40 seconds (only on failure)
- **AI Disabled**: No impact

### Optimization Tips
1. **Reduce timeout** for faster failures (if AI is often slow)
2. **Reduce retries** to fail faster (if AI is unreliable)
3. **Disable AI** temporarily during AI service maintenance

## Troubleshooting

### Issue: AI recommendations always null
**Checks**:
1. Verify `AI_API_ENABLED=true` in `.env`
2. Check AI API base URL is correct
3. Test AI API directly with curl
4. Check network connectivity
5. Review logs for error messages

### Issue: Slow response times
**Solutions**:
1. Reduce `AI_API_TIMEOUT` to fail faster
2. Reduce `AI_API_RETRY_TIMES` to retry less
3. Check AI API performance
4. Consider implementing queue-based processing

### Issue: AI API returning errors
**Checks**:
1. Verify request body format matches AI API expectations
2. Check AI API logs/documentation
3. Verify locale is correctly detected
4. Test with curl to isolate issue

## Disabling AI Integration

### Temporary Disable
Set in `.env`:
```env
AI_API_ENABLED=false
```

### Permanent Disable
Remove AI service injection from `ExamResultService` constructor and related code.

## Deployment Checklist

- [ ] Configure `AI_API_BASE_URL` in production `.env`
- [ ] Set appropriate `AI_API_TIMEOUT` for production
- [ ] Configure `AI_API_RETRY_TIMES` based on needs
- [ ] Test AI API connectivity from production server
- [ ] Verify both English and Arabic endpoints work
- [ ] Monitor logs for AI API performance
- [ ] Set up alerts for high failure rates
- [ ] Clear config cache: `php artisan config:clear`
- [ ] Regenerate Swagger docs: `php artisan l5-swagger:generate`

## API Documentation

Full API documentation including AI recommendations is available at:
- Development: `http://localhost/api/documentation`
- Production: `https://your-domain.com/api/documentation`

## Support

For AI API issues, contact the AI service team.
For integration issues, check application logs and this guide.

