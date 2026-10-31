# Forum Registration Notification Emails

## 1. Student Email

**Subject:** WeVoi Forum Registration Confirmation

**Content:**

```
Dear {Student Name},

Thank you for successfully registering for WeVoi Forum!

Your Registration Details:
- Forum Name: {Forum Name}
- Forum Date: {Forum Date}
- Registration Status: {Registration Status}
- Payment Status: {Payment Status}
{if price exists: - Registration Fee: {Price}}

Your registration has been completed. All updates regarding the Forum will be sent to you via email.

If you have any questions, please feel free to contact us.

Best regards,
WeVoi Team
```

---

## 2. Supervisor Email

**Subject:** Your Student's Forum Registration

**Content:**

```
Dear {Supervisor Name},

Your student {Student Name} has successfully registered for WeVoi Forum.

Student Information:
- Student Name: {Student Name}
- Student Email: {Student Email}
- Forum Name: {Forum Name}
- Forum Date: {Forum Date}
- Registration Status: {Registration Status}
- Payment Status: {Payment Status}

Your student's registration has been completed. All updates regarding the Forum will be sent to both you and the student via email.

If you have any questions, please feel free to contact us.

Best regards,
WeVoi Team
```

---

## 3. Parent Email

**Subject:** Your Child's Forum Registration

**Content:**

```
Dear {Parent Name},

Your child {Student Name} has successfully registered for WeVoi Forum.

Student Information:
- Student Name: {Student Name}
- Student Email: {Student Email}
- Forum Name: {Forum Name}
- Forum Date: {Forum Date}
- Registration Status: {Registration Status}
- Payment Status: {Payment Status}

Your child's registration has been completed. All updates regarding the Forum will be sent to both you and the student via email.

If you have any questions, please feel free to contact us.

Best regards,
WeVoi Team
```

---

## 4. Supervisor Self-Registration Email (When supervisor registers themselves)

**Subject:** WeVoi Forum Registration Confirmation

**Content:**

```
Dear {Supervisor Name},

Thank you for successfully registering for WeVoi Forum!

Your Registration Details:
- Forum Name: {Forum Name}
- Forum Date: {Forum Date}
- Registration Status: {Registration Status}
- Payment Status: {Payment Status}
{if price exists: - Registration Fee: {Price}}

Your registration has been completed. All updates regarding the Forum will be sent to you via email.

If you have any questions, please feel free to contact us.

Best regards,
WeVoi Team
```

---

## 5. Parent Self-Registration Email (When parent registers themselves)

**Subject:** WeVoi Forum Registration Confirmation

**Content:**

```
Dear {Parent Name},

Thank you for successfully registering for WeVoi Forum!

Your Registration Details:
- Forum Name: {Forum Name}
- Forum Date: {Forum Date}
- Registration Status: {Registration Status}
- Payment Status: {Payment Status}
{if price exists: - Registration Fee: {Price}}

Your registration has been completed. All updates regarding the Forum will be sent to you via email.

If you have any questions, please feel free to contact us.

Best regards,
WeVoi Team
```

---

## Variables to be used:

- `{Student Name}` - Student's full name
- `{Student Email}` - Student's email address
- `{Supervisor Name}` - Supervisor's full name
- `{Parent Name}` - Parent's full name
- `{Forum Name}` - Forum competition name
- `{Forum Date}` - Forum date
- `{Registration Status}` - APPLICATION_ACCEPTED
- `{Payment Status}` - PENDING, PAID, or FREE
- `{Price}` - Registration fee (if applicable)
