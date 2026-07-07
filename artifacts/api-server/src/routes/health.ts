import { Router, type IRouter } from "express";
import { HealthCheckResponse } from "@workspace/api-zod";

const router: IRouter = Router();

// Deployment health probes hit the service's base path (`/api`) directly.
// Answer it locally with a fast 200 so it never falls through to the slow
// Laravel proxy (which 404s and stalls the deploy's Promote step).
router.get("/", (_req, res) => {
  const data = HealthCheckResponse.parse({ status: "ok" });
  res.json(data);
});

router.get("/healthz", (_req, res) => {
  const data = HealthCheckResponse.parse({ status: "ok" });
  res.json(data);
});

export default router;
