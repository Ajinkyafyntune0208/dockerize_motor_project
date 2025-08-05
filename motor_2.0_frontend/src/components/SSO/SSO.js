import { useEffect, useState } from "react";

export function useLoginWidget() {
  const [status, setStatus] = useState(false);
  const WinFn = (bool) => window.loggedIn?.next(bool);

  //Auto Login
  useEffect(() => {
    if (localStorage?.userInfo || localStorage?.items) {
      localStorage.setItem("SSO_user", JSON.stringify(localStorage?.userInfo));
      localStorage.setItem("SSO_user_motor", localStorage?.token);
      setStatus(true);
    }
  }, []);

  function setUser(user) {
    if (user && user.token) {
      localStorage.setItem("SSO_user", JSON.stringify(user));
      localStorage.setItem("SSO_user_motor", user?.token);
      WinFn(true);
    } else {
      // logOut();
    }
  }

  function logOut() {
    localStorage.removeItem("SSO_user");
    localStorage.removeItem("SSO_user_motor");
    localStorage.removeItem("userInfo");
    localStorage.removeItem("token");
    WinFn(false);
  }

  useEffect(() => {
    if (window.loginWidget) {
      const elem = window.loginWidget;
      try {
        new window.RB_AMS_SDK({
          userInfo: elem,
          islogIn: setUser,
          UserlogOut: logOut,
          amsurl:
            import.meta.env.VITE_PROD === "YES"
              ? window.location.hostname.includes("renewbuyinsurance")
                ? "https://accounts.renewbuyinsurance.com/"
                : "https://accounts.renewbuy.com/"
              : window.location.hostname.includes("rbstaging")
              ? "https://accounts.rbstaging.in/"
              : "https://accounts.renewbuyinsurance.in/",
        });
        setStatus(true);
      } catch (err) {
        console.error(err);
        // setStatus(false);
      }
    }
  }, [window.loginWidget]);

  return status;
}
