import React from "react";
import { useLocation } from "react-router-dom";
import {
  NotFoundContainer,
  NotFound,
  NotFound404,
  H1Tag,
  H2Tag,
} from "./style";

export const NewError = () => {
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  let error = query.get("error");
  error = error && JSON.parse(atob(error))?.msg;
  let str = error?.split(" ").length > 6;

  return (
    <NotFoundContainer>
      <NotFound>
        <NotFound404>
          <H1Tag str={str}>Oops!</H1Tag>
          <H2Tag>{error ? error : "Something went wrong."}</H2Tag>
        </NotFound404>
      </NotFound>
    </NotFoundContainer>
  );
};
