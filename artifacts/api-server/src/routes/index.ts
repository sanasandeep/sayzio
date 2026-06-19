import { Router, type IRouter } from "express";
import healthRouter from "./health";
import contactRouter from "./contact";
import { laravelProxy } from "../middlewares/laravel-proxy";

const router: IRouter = Router();

router.use(healthRouter);
router.use(contactRouter);

// Fallthrough: any `/api` request not handled by this api-server's own routes
// above is forwarded to the Laravel backend so the mobile app works in the
// Replit preview. Must stay last so health/contact are served locally.
router.use(laravelProxy);

export default router;
