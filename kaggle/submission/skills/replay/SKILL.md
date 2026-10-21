---
name: test_driven_replay
description: "Disciplined dynamic replay cycle: minimal reproduction via pytest or repro script, surgical localization, atomic edit_file modifications, and iterative test feedback before submission."
---

# Skill: Test-Driven Replay & Repair Loop

This skill enforces a disciplined engineering cycle for debugging and fixing Python issues.

## Mandatory Sequential Workflow

1. **Minimal Reproduction**:
   - Identify the existing test or write a minimal reproduction script exercising the failure reported in the ticket.
   - Run reproduction using `run_command("python3 -m pytest <test_file> -k <test_name> -q --tb=short | tail -n 40")` or `run_command("python3 /tmp/repro.py")`.
   - Confirm initial failure (exit code != 0) and inspect the stack trace.

2. **Focused Localization**:
   - Identify the exact faulty file and line numbers from the stack trace and issue description.
   - Read only the required code ranges with `read_file(filepath, start_line, end_line)`.

3. **Atomic Modification**:
   - Use `edit_file(filepath, old_string, new_string)`.
   - Ensure `old_string` precisely matches indentation and content in the original file.
   - Modify only the minimum necessary lines to resolve the root cause.

4. **Replay & Verification**:
   - Immediately rerun the reproduction command via `run_command`.
   - If tests pass cleanly: ensure no regressions were introduced.
   - If tests fail: analyze the residual error and apply a targeted incremental fix.

5. **Final Submission**:
   - Clean up any temporary reproducer files (`rm -f /tmp/repro.py`).
   - Check `run_command("git status --short")`.
   - Call `submit_patch()` as the final tool call.
