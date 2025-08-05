import React, { Suspense, lazy } from "react";
import { lazily } from "react-lazily";
import { BrowserRouter, Switch, Route, Redirect } from "react-router-dom";
//prettier-ignore
import { Loader, AbiblHeader, Header, Layout, AbiblFooter, Footer,
         SriyahFooter, BajajFooter, TataFooter, BajajB2CFooter, AceFooter,
         RenewbuyFooter
       } from "components";
import { Payment } from "modules/payment-gateway/payment";
import { subroutes } from "modules/type";
import { reloadPage } from "utils";
import { userIdentifier, authPdf } from "modules/login/login.slice";
import PaymentPdf from "modules/payment-pdf/paymentPdf";
import ConformationUpdates from "modules/consent/conformation-update";
import ReviewForm from "modules/review/review";
import { Disclaimer } from "components/privacy-policy/kmd-policy";
import ResumeJourney from "modules/resume-journey/resume-journey";
//Lazy load
const ErrorPage = lazy(() => import("components/ErrorPages/errorPage"));
const JournerSuccess = lazy(() =>
  import("components/ErrorPages/journey-success")
);
const Error404 = lazy(() => import("components/ErrorPages/404"));
const PaymentFail = lazy(() => import("components/ErrorPages/payment-failure"));
const GeneratePdf = lazy(() => import("modules/GeneratePdf/GeneratePdf"));
const PrivacyPolicy = lazy(() =>
  import("components/privacy-policy/privacyPolicy")
);
const InternalServerErrorPage = lazy(() => import("components/ErrorPages/500"));
const Config = lazy(() => import("config/config"));
const { NewError } = lazily(() => import("components/ErrorPages/NewError"));
const { Home } = lazily(() => import("modules"));
const PaymentSuccess = lazy(() =>
  import("components/ErrorPages/payment-success")
);
//named lazy imports
//prettier-ignore
const { QuotesPage, Proposal, Login, ComparePage,
        LandingPage, Inspection
      } = lazily(() => import("modules"));

//Auth
const PrivateRoute = ({ component: Component, props, ...rest }) => {
  const typeRoute = window.location.pathname.split("/");
  const type =
    typeRoute?.length && import.meta.env.VITE_BASENAME === "NA"
      ? typeRoute.length >= 1
        ? typeRoute[1]
        : []
      : typeRoute.length >= 2
      ? typeRoute[2]
      : [];
  const typeRouteIndex = import.meta.env.VITE_BASENAME === "NA" ? 1 : 2;
  const partialRouteCheck =
    typeRoute.length === typeRouteIndex ||
    (typeRoute.length > typeRouteIndex && !typeRoute[typeRouteIndex + 1]);
  return rest?.repurpose &&
    type &&
    [...subroutes].includes(type) &&
    partialRouteCheck ? (
    <Redirect to={`/${type}/lead-page`} />
  ) : (
    <Route
      {...rest}
      render={(props) =>
        type && [...subroutes].includes(type) ? (
          <Component {...props} />
        ) : import.meta.env.VITE_BASENAME !== "NA" ? (
          <Component {...props} />
        ) : (
          <Error404 {...props} />
        )
      }
    />
  );
};

const BajajRedirection = () => {
  return import.meta.env.VITE_PROD === "YES"
    ? import.meta.env.VITE_BASENAME === "general-insurance"
      ? window.location.origin
      : "https://partner.bajajcapitalinsurance.com"
    : import.meta.env.VITE_API_BASE_URL ===
      "https://stageapimotor.bajajcapitalinsurance.com/api"
    ? import.meta.env.VITE_BASENAME === "general-insurance"
      ? window.location.origin
      : "https://partnerstage.bajajcapitalinsurance.com"
    : //UAT
    import.meta.env.VITE_BASENAME === "general-insurance"
    ? "https://buypolicyuat.bajajcapitalinsurance.com/"
    : "https://partneruat.bajajcapitalinsurance.com";
};
const Router = (props) => {
  return (
    <BrowserRouter
      basename={
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }
    >
      <Suspense fallback={<Loader />}>
        <Switch>
          {import.meta.env.VITE_BROKER === "ACE" ? (
            <Route exact path="/">
              <Redirect to="/404" />
            </Route>
          ) : (
            <Route exact path="/">
              <Redirect to="/landing-page" />
            </Route>
          )}
          <Route
            exact
            path="/landing-page"
            component={
              import.meta.env.VITE_BROKER === "ACE" &&
              window.location?.hostname !== "localhost"
                ? () => {
                    reloadPage(
                      "https://crm.aceinsurance.com:5555/Admin/Dashboard"
                    );
                    return <noscript />;
                  }
                : import.meta.env.VITE_BROKER === "BAJAJ" &&
                  window.location?.hostname !== "localhost"
                ? () => {
                    reloadPage(BajajRedirection());
                    return <noscript />;
                  }
                : import.meta.env.VITE_BROKER === "SRIYAH" &&
                  window.location?.hostname !== "localhost"
                ? () => {
                    return <Redirect to="/car/lead-page" />;
                  }
                : LandingPage
            }
          />
          {/* <PrivateRoute exact path="/:type" repurpose={true} /> */}
          {/* <Route exact path="/login" component={Login} /> */}
          <Layout BlockLayout={props?.BlockLayout}>
            {import.meta.env.VITE_BROKER === "ABIBL" ? (
              <AbiblHeader />
            ) : (
              props?.BlockLayout && <Header />
            )}
            <Switch>
              <Route exact path="/500" component={InternalServerErrorPage} />
              {/* {["ACE", "KMD"].includes(import.meta.env.VITE_BROKER) && ( */}
              <Route
                exact
                path="/privacy"
                component={
                  import.meta.env.VITE_BROKER === "HEROCARE"
                    ? Disclaimer
                    : PrivacyPolicy
                }
              />
              {/* )} */}
              <Route exact path="/consent" component={ConformationUpdates} />
              <Route exact path="/feedback" component={ReviewForm} />
              <PrivateRoute exact path="/:type/lead-page" component={Home} />
              <PrivateRoute
                exact
                path="/:type/auto-register"
                component={Home}
              />
              <PrivateRoute exact path="/:type/journey-type" component={Home} />
              <PrivateRoute exact path="/:type/registration" component={Home} />
              <PrivateRoute exact path="/:type/vehicle-type" component={Home} />
              <PrivateRoute
                exact
                path="/:type/vehicle-details"
                component={Home}
              />
              <PrivateRoute exact path="/:type/quotes" component={QuotesPage} />
              <PrivateRoute
                exact
                path="/:type/compare-quote"
                component={ComparePage}
              />
              <PrivateRoute
                exact
                path="/:type/proposal-page"
                component={Proposal}
              />
              <PrivateRoute
                exact
                path="/:type/payment-gateway"
                component={Payment}
              />
              <Route exact path="/payment-success" component={PaymentSuccess} />
              <Route exact path="/payment-failure" component={PaymentFail} />
              <PrivateRoute
                exact
                path="/:type/successful"
                component={JournerSuccess}
              />
              <PrivateRoute exact path="/:type/renewal" component={Home} />
              <Route
                exact
                path="/motor/check-inspection-status"
                component={Inspection}
              />
              <Route exact path="/generate-pdf" component={GeneratePdf} />
              <Route exact path="/payment-status" component={PaymentPdf} />
              <Route exact path="/resume-journey" component={ResumeJourney} />
              <Route
                exact
                path="/config"
                component={
                  localStorage?.configKey &&
                  userIdentifier.includes(atob(localStorage?.configKey))
                    ? Config
                    : Login
                }
              />
              <Route
                exact
                path="/loader"
                component={(props) => <Loader {...props} />}
              />
              <Route
                exact
                path="/error"
                component={
                  import.meta.env.VITE_BROKER === "RB" ? ErrorPage : NewError
                }
              />
              <Route exact path="/404" component={Error404} />
              <PrivateRoute exact path="/:type" repurpose={true} />
              <Route path="*" component={Error404} />
              <Route component={Error404} />
            </Switch>
            {import.meta.env.VITE_BROKER === "ABIBL" ? (
              <AbiblFooter />
            ) : import.meta.env.VITE_BROKER === "SRIYAH" ? (
              <SriyahFooter />
            ) : import.meta.env.VITE_BROKER === "BAJAJ" ? (
              import.meta.env.VITE_BASENAME === "general-insurance" ? (
                <BajajB2CFooter />
              ) : (
                <BajajFooter />
              )
            ) : import.meta.env.VITE_BROKER === "TATA" ? (
              <TataFooter />
            ) : import.meta.env.VITE_BROKER === "ACE" ? (
              <AceFooter />
            ) : import.meta.env.VITE_BROKER === "RB" ? (
              <RenewbuyFooter />
            ) : (
              props?.BlockLayout && <Footer />
            )}
          </Layout>
        </Switch>
      </Suspense>
    </BrowserRouter>
  );
};

export default Router;
