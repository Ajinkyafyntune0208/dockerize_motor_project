import React from "react";
import {
  NotFoundContainer,
  NotFound,
  NotFound404,
  H1Tag,
  H2Tag,
  AnchorTag,
} from "./style";
import { RedirectFn } from "utils";

const Error = () => {
  return (
    <NotFoundContainer>
      <NotFound>
        <NotFound404>
          <H1Tag>Oops!</H1Tag>
          <H2Tag>404 - The Page can't be found</H2Tag>
        </NotFound404>
        <AnchorTag
          onClick={() => {
            window.Android &&
              window.Android.SendToHomePage("Redirecting to homepage");
          }}
          href={RedirectFn()}
        >
          Go To Homepage
        </AnchorTag>
      </NotFound>
    </NotFoundContainer>
  );
};

export default Error;
