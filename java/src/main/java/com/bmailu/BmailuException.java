package com.bmailu;

/** An error from the Bmailu API ({"error":{"code","message"}}) or from the network. */
public class BmailuException extends RuntimeException {
    private final int status;
    private final String code;

    public BmailuException(int status, String code, String message) {
        super(message);
        this.status = status;
        this.code = code;
    }

    public BmailuException(int status, String code, String message, Throwable cause) {
        super(message, cause);
        this.status = status;
        this.code = code;
    }

    /** HTTP status, or 0 when the request did not reach the API. */
    public int getStatus() { return status; }

    /** Machine-readable code, e.g. not_found, validation_error, domain_not_verified. */
    public String getCode() { return code; }

    @Override
    public String toString() {
        return "BmailuException(" + status + ", " + code + "): " + getMessage();
    }
}
